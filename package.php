<?php
require 'libraries/autoload.php';

use Phine\Phar\Builder;
use Phine\Phar\Stub;

@unlink('manialive.phar');

$builder = Builder::create('manialive.phar');

class ManiaLiveIterator extends RecursiveFilterIterator 
{
	public function accept()
	{
		$path = explode(DIRECTORY_SEPARATOR, $this->current()->getPath());
		return !in_array('.git', $path, true) && $this->current()->getFilename() != '.git';
	}
}

$iterator = new RecursiveIteratorIterator(new ManiaLiveIterator(new RecursiveDirectoryIterator(__DIR__), RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY );

$builder->buildFromIterator($iterator, __DIR__);

$builder->setStub(
		Stub::create()
				->mapPhar('manialive.phar')
				->addRequire('bootstrapper.php')
				->getStub());
?>