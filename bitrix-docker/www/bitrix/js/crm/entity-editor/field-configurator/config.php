<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

return [
	'css' => 'dist/field-configurator.bundle.css',
	'js' => 'dist/field-configurator.bundle.js',
	'rel' => [
		'crm.entity-selector',
		'main.core.events',
		'main.core',
	],
	'skip_core' => false,
];
