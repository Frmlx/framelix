<?php

namespace Network;

use Framelix\Framelix\Exception\SoftError;
use Framelix\Framelix\Network\Response;
use Framelix\Framelix\Utils\Buffer;
use Framelix\Framelix\Utils\FileUtils;
use Framelix\FramelixTests\Storable\TestStorableFile;
use Framelix\FramelixTests\TestCase;

use function file_get_contents;
use function file_put_contents;
use function http_response_code;
use function ob_get_level;
use function unlink;

final class ResponseTest extends TestCase
{

    public function tests(): void
    {
        $storableFile = new TestStorableFile();
        FileUtils::deleteDirectory("/framelix/userdata/" . $storableFile->relativePathOnDisk);
        mkdir("/framelix/userdata/" . $storableFile->relativePathOnDisk);

        Buffer::start();
        try {
            Response::download("@filecontent", "foo");
        } catch (SoftError) {
            $this->assertSame("filecontent", Buffer::get());
        }
        Buffer::start();
        $this->assertExceptionOnCall(function () {
            Response::download(__FILE__, "foo", null, function () {
            });
        }, [], SoftError::class);
        $this->assertSame(file_get_contents(__FILE__), Buffer::get());

        $file = new TestStorableFile();
        $file->relativePathOnDisk = "test.txt";
        $filePath = $file->getPath(false);

        // not exist test
        Buffer::start();
        $this->assertExceptionOnCall(function () use ($filePath) {
            file_put_contents($filePath, "foobar");
            Response::download($filePath);
        }, [], SoftError::class);
        $this->assertSame("foobar", Buffer::get());
        unlink($file->getPath());

        // not exist test
        $this->assertExceptionOnCall(function () {
            Response::download(__FILE__ . "NotExist");
        }, [], SoftError::class);

        // not exist test
        $this->assertExceptionOnCall(function () use ($file) {
            Response::download($file);
        }, [], SoftError::class);

        // test form validation response
        http_response_code(200);
        $oldIndex = Buffer::$startBufferIndex;
        Buffer::start();
        Buffer::$startBufferIndex = ob_get_level();
        $this->assertExceptionOnCall(function () {
            Response::stopWithFormValidationResponse(['test' => 'Error']);
        }, [], SoftError::class);
        Buffer::$startBufferIndex = $oldIndex;
        $this->assertSame(200, http_response_code());
        $this->assertSame('{"toastMessages":[],"errorMessages":{"test":"Error"},"buffer":""}', Buffer::get());
    }
}
