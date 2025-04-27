<?php declare(strict_types = 1);

namespace Tests\Cases\DI;

use Contributte\Tester\Toolkit;
use Contributte\Tester\Utils\ContainerBuilder;
use Contributte\Tester\Utils\Neonkit;
use Doctrine\DBAL\Types\Type;
use Nette\DI\Compiler;
use Nettrine\DBAL\DI\DbalExtension;
use Nettrine\Extensions\Oroinc\DI\OroincBehaviorExtension;
use Nettrine\ORM\DI\OrmExtension;
use ReflectionClass;
use Tester\Assert;

require __DIR__ . '/../../bootstrap.php';

Toolkit::setUp(static function (): void {
	$rc = new ReflectionClass(Type::class);
	$rc->setStaticPropertyValue('typeRegistry', null);
});

// MySQL
Toolkit::test(static function (): void {
	$container = ContainerBuilder::of()
		->withCompiler(static function (Compiler $compiler): void {
			$compiler->addExtension('nettrine.dbal', new DbalExtension());
			$compiler->addExtension('nettrine.orm', new OrmExtension());
			$compiler->addConfig(Neonkit::load(
				<<<'NEON'
				nettrine.dbal:
					connections:
						default:
							driver: mysqli
							host: localhost
							port: "3306"
							user: test
							password: test
							serverVersion: 11.0.0

				nettrine.orm:
					managers:
						default:
							connection: default
							mapping:
								App:
									directories: [App/Database]
									namespace: App\Database
				NEON
			));
			$compiler->addExtension('nettrine.extensions.oroinc', new OroincBehaviorExtension());
			$compiler->addConfig([
				'nettrine.extensions.oroinc' => [
					'connections' => [
						'default' => [
							'driver' => 'pdo_mysql',
						],
					],
				],
			]);
		})->build();

	$container->initialize();
	Assert::notNull($container->getByName('nettrine.orm.managers.default.configuration'));
});

// PostgreSQL
Toolkit::test(static function (): void {
	$container = ContainerBuilder::of()
		->withCompiler(static function (Compiler $compiler): void {
			$compiler->addExtension('nettrine.dbal', new DbalExtension());
			$compiler->addExtension('nettrine.orm', new OrmExtension());
			$compiler->addConfig(Neonkit::load(
				<<<'NEON'
				nettrine.dbal:
					connections:
						default:
							driver: pdo_pgsql
							host: localhost
							port: 5432
							user: root
							password: root
							dbname: nettrine

				nettrine.orm:
					managers:
						default:
							connection: default
							mapping:
								App:
									directories: [App/Database]
									namespace: App\Database
				NEON
			));
			$compiler->addExtension('nettrine.extensions.oroinc', new OroincBehaviorExtension());
			$compiler->addConfig([
				'nettrine.extensions.oroinc' => [
					'connections' => [
						'default' => [
							'driver' => 'pdo_mysql',
						],
					],
				],
			]);
		})->build();

	$container->initialize();
	Assert::notNull($container->getByName('nettrine.orm.managers.default.configuration'));
});

// Types
Toolkit::test(static function (): void {
	$container = ContainerBuilder::of()
		->withCompiler(static function (Compiler $compiler): void {
			$compiler->addExtension('nettrine.dbal', new DbalExtension());
			$compiler->addExtension('nettrine.orm', new OrmExtension());
			$compiler->addConfig(Neonkit::load(
				<<<'NEON'
				nettrine.dbal:
					connections:
						default:
							driver: mysqli
							host: localhost
							port: "3306"
							user: test
							password: test
							serverVersion: 11.0.0

				nettrine.orm:
					managers:
						default:
							connection: default
							mapping:
								App:
									directories: [App/Database]
									namespace: App\Database
				NEON
			));
			$compiler->addExtension('nettrine.extensions.oroinc', new OroincBehaviorExtension());
			$compiler->addConfig([
				'nettrine.extensions.oroinc' => [
					'connections' => [
						'default' => [
							'driver' => 'mysql',
						],
					],
				],
			]);
			$compiler->addConfig(Neonkit::load(<<<'NEON'
				services:
					- Doctrine\DBAL\Connection(
						[],
						Doctrine\DBAL\Driver\Mysqli\Driver()
					)
			NEON
			));
		})->build();

	$container->initialize();
	Assert::notNull($container->getByName('nettrine.orm.managers.default.configuration'));
});
