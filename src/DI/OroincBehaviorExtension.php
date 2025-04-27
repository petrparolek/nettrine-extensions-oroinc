<?php declare(strict_types = 1);

namespace Nettrine\Extensions\Oroinc\DI;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Oro\DBAL\Types\MoneyType;
use Oro\DBAL\Types\PercentType;
use Oro\ORM\Query\AST\Functions\Cast;
use Oro\ORM\Query\AST\Functions\DateTime\ConvertTz;
use Oro\ORM\Query\AST\Functions\Numeric\Pow;
use Oro\ORM\Query\AST\Functions\Numeric\Round;
use Oro\ORM\Query\AST\Functions\Numeric\Sign;
use Oro\ORM\Query\AST\Functions\Numeric\TimestampDiff;
use Oro\ORM\Query\AST\Functions\SimpleFunction;
use Oro\ORM\Query\AST\Functions\String\ConcatWs;
use Oro\ORM\Query\AST\Functions\String\DateFormat;
use Oro\ORM\Query\AST\Functions\String\GroupConcat;
use Oro\ORM\Query\AST\Functions\String\Replace;
use stdClass;

/**
 * @property-read stdClass $config
 */
final class OroincBehaviorExtension extends CompilerExtension
{

	private const NEW_TYPES = [
		MoneyType::TYPE => [
			MoneyType::class,
			'decimal',
		],
		PercentType::TYPE => [
			PercentType::class,
			'decimal',
		],
	];

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'connections' => Expect::arrayOf(
				Expect::structure([
					'driver' => Expect::anyOf(
						'mysql',
						'mysql2',
						'pdo_mysql', // mysql
						'pgsql',
						'postgres',
						'postgresql',
						'pdo_pgsql' // postgre
					),
				])
			)->min(1)->required()]);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		$connectionsConfig = $this->config->connections;

		foreach ($connectionsConfig as $connection => $config) {
			if ($config->driver === null) {
				return;
			}

			$configurationDefinition = $builder->findByType(Configuration::class)['nettrine.orm.managers.' . $connection . '.configuration'];
			assert($configurationDefinition instanceof ServiceDefinition);

			$this->registerCrossPlatformFunctions($configurationDefinition);
		}
	}

	public function afterCompile(ClassType $class): void
	{
		$initialize = $class->getMethod('initialize');

		foreach (self::NEW_TYPES as $name => [$className, $dbType]) {
			$initialize->addBody(sprintf(
				'%s::addType(\'%s\', \'%s\');',
				Type::class,
				$name,
				$className
			));
		}
	}

	/**
	 * @param string[] $functions
	 */
	private function registerFunctions(ServiceDefinition $configurationDefinition, array $functions, string $method): void
	{
		foreach ($functions as $name => $class) {
			$configurationDefinition->addSetup($method, [$name, $class]);
		}
	}

	/**
	 * @param string[] $functions
	 */
	private function registerDatetimeFunctions(ServiceDefinition $configurationDefinition, array $functions): void
	{
		$this->registerFunctions($configurationDefinition, $functions, 'addCustomDatetimeFunction');
	}

	/**
	 * @param string[] $functions
	 */
	private function registerNumericFunctions(ServiceDefinition $configurationDefinition, array $functions): void
	{
		$this->registerFunctions($configurationDefinition, $functions, 'addCustomNumericFunction');
	}

	/**
	 * @param string[] $functions
	 */
	private function registerStringFunctions(ServiceDefinition $configurationDefinition, array $functions): void
	{
		$this->registerFunctions($configurationDefinition, $functions, 'addCustomStringFunction');
	}

	private function registerCrossPlatformFunctions(ServiceDefinition $configurationDefinition): void
	{
		$datetimeFunctions = [
			'date' => SimpleFunction::class,
			'time' => SimpleFunction::class,
			'timestamp' => SimpleFunction::class,
			'convert_tz' => ConvertTz::class,
		];
		$this->registerDatetimeFunctions($configurationDefinition, $datetimeFunctions);

		$numericFunctions = [
			'timestampdiff' => TimestampDiff::class,
			'dayofyear' => SimpleFunction::class,
			'dayofmonth' => SimpleFunction::class,
			'dayofweek' => SimpleFunction::class,
			'week' => SimpleFunction::class,
			'day' => SimpleFunction::class,
			'hour' => SimpleFunction::class,
			'minute' => SimpleFunction::class,
			'month' => SimpleFunction::class,
			'quarter' => SimpleFunction::class,
			'second' => SimpleFunction::class,
			'year' => SimpleFunction::class,
			'sign' => Sign::class,
			'pow' => Pow::class,
			'round' => Round::class,
			'ceil' => SimpleFunction::class,
		];
		$this->registerNumericFunctions($configurationDefinition, $numericFunctions);

		$stringFunctions = [
			'md5' => SimpleFunction::class,
			'group_concat' => GroupConcat::class,
			'concat_ws' => ConcatWs::class,
			'cast' => Cast::class,
			'replace' => Replace::class,
			'date_format' => DateFormat::class,
		];
		$this->registerStringFunctions($configurationDefinition, $stringFunctions);
	}

}
