<?php

require __DIR__ . '/vendor/tenantcloud/php-cs-fixer-rule-sets/src/TenantCloud/PhpCsFixer/RuleSet/TenantCloudSet.php';

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use TenantCloud\PhpCsFixer\RuleSet\TenantCloudSet;

$finder = Finder::create()
	->in('src')
	->in('tests')
	->name('*.php')
	->notName('_*.php')
	->ignoreVCS(true);

return (new Config())
	->setFinder($finder)
	->setRiskyAllowed(true)
	->setIndent("\t")
	->setRules([
		...(new TenantCloudSet())->rules(),
	]);
