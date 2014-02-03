<?php
require 'libraries/autoload.php';

use Phine\Phar\Builder;
use Phine\Phar\Stub;

@unlink('manialive.phar');

$builder = Builder::create('manialive.phar');

$builder->buildFromDirectory('.');

$builder->setStub(
		Stub::create()
			->mapPhar('manialive.phar')
			->addRequire('bootstrapper.php')
			->getStub());
?>