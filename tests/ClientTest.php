<?php

namespace ChrisWhite\B2\Tests;

use ChrisWhite\B2\Client;
use ChrisWhite\B2\Bucket;
use ChrisWhite\B2\File;
use ChrisWhite\B2\Exceptions\BucketAlreadyExistsException;
use ChrisWhite\B2\Exceptions\BadJsonException;
use ChrisWhite\B2\Exceptions\ValidationException;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Stream;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    use TestHelper;

    public function testCreatePublicBucket()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'create_bucket_public.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        // Test that we get a public bucket back after creation
        $bucket = $client->createBucket('Test bucket', Bucket::TYPE_PUBLIC);
        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertEquals('Test bucket', $bucket->getName());
        $this->assertEquals(Bucket::TYPE_PUBLIC, $bucket->getType());
    }

    public function testCreatePrivateBucket()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'create_bucket_private.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        // Test that we get a private bucket back after creation
        $bucket = $client->createBucket('Test bucket', Bucket::TYPE_PRIVATE);
        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertEquals('Test bucket', $bucket->getName());
        $this->assertEquals(Bucket::TYPE_PRIVATE, $bucket->getType());
    }

    public function testBucketAlreadyExistsExceptionThrown()
    {
        $this->setExpectedException(BucketAlreadyExistsException::class);

        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(400, [], 'create_bucket_exists.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);
        $client->createBucket('I already exist', Bucket::TYPE_PRIVATE);
    }

    public function testInvalidBucketTypeThrowsException()
    {
        $this->setExpectedException(ValidationException::class);

        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);
        $client->createBucket('bucket-name', 'invalid-type');
    }

    public function testUpdateBucketToPrivate()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'update_bucket_to_private.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $bucket = $client->updateBucket('test-bucket', Bucket::TYPE_PRIVATE);
        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertEquals('bucketId', $bucket->getId());
        $this->assertEquals('test-bucket', $bucket->getName());
        $this->assertEquals(Bucket::TYPE_PRIVATE, $bucket->getType());
    }

    public function testUpdateBucketToPublic()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'update_bucket_to_public.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $bucket = $client->updateBucket('test-bucket', Bucket::TYPE_PRIVATE);
        $this->assertInstanceOf(Bucket::class, $bucket);
        $this->assertEquals('bucketId', $bucket->getId());
        $this->assertEquals('test-bucket', $bucket->getName());
        $this->assertEquals(Bucket::TYPE_PUBLIC, $bucket->getType());
    }

    public function testList3Buckets()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'list_buckets_3.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $buckets = $client->listBuckets();
        $this->assertInternalType('array', $buckets);
        $this->assertCount(3, $buckets);
        $this->assertInstanceOf(Bucket::class, $buckets[0]);
    }

    public function testEmptyArrayWithNoBuckets()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'list_buckets_0.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $buckets = $client->listBuckets();
        $this->assertInternalType('array', $buckets);
        $this->assertCount(0, $buckets);
    }

    public function testDeleteBucket()
    {
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'delete_bucket.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $this->assertTrue($client->deleteBucket('testId'));
    }

    public function testBadJsonThrownDeletingNonExistentBucket()
    {
        $this->setExpectedException(BadJsonException::class);

        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(400, [], 'delete_bucket_non_existent.json')
        ]);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);

        $client->deleteBucket('i-dont-exist');
    }

    public function testUploadingResource()
    {
        $container = [];
        $history = Middleware::history($container);
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'get_upload_url.json'),
            $this->buildResponseFromStub(200, [], 'upload.json')
        ], $history);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);
        $content = 'The quick brown box jumps over the lazy dog';
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, $content);
        rewind($resource);

        $file = $client->upload('bucketId', 'test.bin', $resource);
        $this->assertInstanceOf(File::class, $file);

        // We'll also check the Guzzle history to make sure the upload request got created correctly.
        $uploadRequest = $container[2]['request'];
        $this->assertEquals('uploadUrl', $uploadRequest->getRequestTarget());
        $this->assertEquals('authToken', $uploadRequest->getHeader('Authorization')[0]);
        $this->assertEquals(strlen($content), $uploadRequest->getHeader('Content-Length')[0]);
        $this->assertEquals('test.bin', $uploadRequest->getHeader('X-Bz-File-Name')[0]);
        $this->assertEquals(sha1($content), $uploadRequest->getHeader('X-Bz-Content-Sha1')[0]);
        $this->assertEquals(round(microtime(true) * 1000), $uploadRequest->getHeader('X-Bz-Info-src_last_modified_millis')[0], '', 100);
        $this->assertInstanceOf(Stream::class, $uploadRequest->getBody());
    }

    public function testUploadingString()
    {
        $container = [];
        $history = Middleware::history($container);
        $guzzle = $this->buildGuzzleFromResponses([
            $this->buildResponseFromStub(200, [], 'authorize_account.json'),
            $this->buildResponseFromStub(200, [], 'get_upload_url.json'),
            $this->buildResponseFromStub(200, [], 'upload.json')
        ], $history);

        $client = new Client('testId', 'testKey', ['client' => $guzzle]);
        $content = 'The quick brown box jumps over the lazy dog';
        $file = $client->upload('bucketId', 'test.bin', $content);
        $this->assertInstanceOf(File::class, $file);

        // We'll also check the Guzzle history to make sure the upload request got created correctly.
        $uploadRequest = $container[2]['request'];
        $this->assertEquals('uploadUrl', $uploadRequest->getRequestTarget());
        $this->assertEquals('authToken', $uploadRequest->getHeader('Authorization')[0]);
        $this->assertEquals(strlen($content), $uploadRequest->getHeader('Content-Length')[0]);
        $this->assertEquals('test.bin', $uploadRequest->getHeader('X-Bz-File-Name')[0]);
        $this->assertEquals(sha1($content), $uploadRequest->getHeader('X-Bz-Content-Sha1')[0]);
        $this->assertEquals(round(microtime(true) * 1000), $uploadRequest->getHeader('X-Bz-Info-src_last_modified_millis')[0], '', 100);
        $this->assertInstanceOf(Stream::class, $uploadRequest->getBody());
    }
}