<?php

use Prewk\XmlFaker;
use Prewk\XmlStringStreamer;

class XmlStringStreamerTest extends PHPUnit_Framework_TestCase
{
	public function testChunkCallback()
	{
		$maxFileSize = 10 * 1024;
		$chunkSize = 100;

		$simpleBlueprint = simplexml_load_file(__dir__ . "/simpleBlueprint.xml");
		$xmlFaker = new XmlFaker($simpleBlueprint);

		$tmpFile = tempnam("/tmp", "xml-string-streamer-test");

		$xmlFaker->asFile($tmpFile, XmlFaker::BYTE_COUNT_RESTRICTION_MODE, $maxFileSize);

		$counter = 0;
		$totalReadBytes = 0;
		$streamProvider = new XmlStringStreamer\FileStreamProvider($tmpFile, $chunkSize, function($buffer, $readBytes) use (&$counter, &$totalReadBytes) {
			$counter++;
			$totalReadBytes = $readBytes;
		});

		$streamer = new XmlStringStreamer($streamProvider, function($xmlNode) {

		});
		$streamer->parse();

		$expectedRuns = $maxFileSize / $chunkSize;

		$fileSize = filesize($tmpFile);
		unlink($tmpFile);
		
		$this->assertGreaterThanOrEqual($expectedRuns, $counter + 5, "Number of chunk callback runs should be in the vicinity of the max files size / chunk size");
		$this->assertEquals($fileSize, $totalReadBytes, "The file size of the read xml file should match the total read bytes");
	}

	public function testLargeSimpleXml()
	{
		$nodeNo = 100000;

		$simpleBlueprint = simplexml_load_file(__dir__ . "/simpleBlueprint.xml");
		$xmlFaker = new XmlFaker($simpleBlueprint);

		$tmpFile = tempnam("/tmp", "xml-string-streamer-test");

		$xmlFaker->asFile($tmpFile, XmlFaker::NODE_COUNT_RESTRICTION_MODE, $nodeNo);

		$memoryUsageBefore = memory_get_usage(true);
		$streamProvider = new XmlStringStreamer\FileStreamProvider($tmpFile, 100);

		$counter = 0;
		$streamer = new XmlStringStreamer($streamProvider, function($xmlNode) use (&$counter) {
			$counter++;
		});
		$streamer->parse();

		$memoryUsageAfter = memory_get_usage(true);

		$this->assertEquals($nodeNo, $counter, "There should be exactly $nodeNo nodes captured");
		$this->assertLessThan(500 * 1024, $memoryUsageAfter - $memoryUsageBefore, "Memory usage should not go higher than 500 KiB");

		unlink($tmpFile);
	}

	public function testXmlWithComments()
	{
		$streamProvider = new XmlStringStreamer\FileStreamProvider(__dir__ . "/xmlWithComments.xml", 70);
		
		$expectedStrings = array("Foo", "Bar", "Baz", "Foo", "Bar");

		$foundStrings = array();

		$streamer = new XmlStringStreamer($streamProvider, function($xmlNode) use (&$foundStrings) {
			$xml = simplexml_load_string($xmlNode);
			$foundStrings[] = trim((string)$xml->child);
		});
		
		$streamer->parse();
		$this->assertEquals($expectedStrings, $foundStrings, "The strings should be extracted with xml comments in the document");
	}

	public function testXmlWithCDATA()
	{
		$streamProvider = new XmlStringStreamer\FileStreamProvider(__dir__ . "/xmlWithCDATA.xml", 70);
		
		$expectedStrings = array("Foo", "Bar", "Baz", "Foo", "Bar");

		$foundStrings = array();

		$streamer = new XmlStringStreamer($streamProvider, function($xmlNode) use (&$foundStrings) {
			$xml = simplexml_load_string($xmlNode);
			$foundStrings[] = trim((string)$xml->child);
		});
		
		$streamer->parse();
		$this->assertEquals($expectedStrings, $foundStrings, "The strings should be extracted with xml CDATA in the document");
	}

	public function testXmlWithDoctype()
	{
		$streamProvider = new XmlStringStreamer\FileStreamProvider(__dir__ . "/xmlWithDoctype.xml", 70);
		
		$expectedStrings = array("Foo", "Bar", "Baz", "Foo", "Bar");

		$foundStrings = array();

		$streamer = new XmlStringStreamer($streamProvider, function($xmlNode) use (&$foundStrings) {
			$xml = simplexml_load_string($xmlNode);
			$foundStrings[] = trim((string)$xml->child);
		});
		
		$streamer->parse();
		$this->assertEquals($expectedStrings, $foundStrings, "The strings should be extracted with xml DOCTYPE in the document");
	}
}