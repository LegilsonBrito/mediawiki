<?php

/**
 * @covers ParserFactory
 */
class ParserFactoryTest extends MediaWikiTestCase {
	/**
	 * For backwards compatibility, all parameters to the parser constructor are optional and
	 * default to the appropriate global service, so it's easy to forget to update ParserFactory to
	 * actually pass the parameters it's supposed to.
	 */
	public function testConstructorArgNum() {
		$factoryConstructor = new ReflectionMethod( 'ParserFactory', '__construct' );
		$instanceConstructor = new ReflectionMethod( 'Parser', '__construct' );
		// Subtract one for the ParserFactory itself
		$this->assertSame( $instanceConstructor->getNumberOfParameters() - 1,
			$factoryConstructor->getNumberOfParameters(),
			'Parser and ParserFactory constructors have an inconsistent number of parameters. ' .
			'Did you add a parameter to one and not the other?' );
	}

	public function testAllArgumentsWerePassed() {
		$factoryConstructor = new ReflectionMethod( 'ParserFactory', '__construct' );
		$mocks = [];
		foreach ( $factoryConstructor->getParameters() as $index => $param ) {
			$type = (string)$param->getType();
			if ( $index === 0 ) {
				$val = $this->createMock( 'MediaWiki\Config\ServiceOptions' );
			} elseif ( $type === 'array' ) {
				$val = [ 'porcupines will tell me your secrets' . count( $mocks ) ];
			} elseif ( class_exists( $type ) || interface_exists( $type ) ) {
				$val = $this->createMock( $type );
			} elseif ( $type === '' ) {
				// Optimistically assume a string is okay
				$val = 'I will de-quill them first' . count( $mocks );
			} else {
				$this->fail( "Unrecognized parameter type $type in ParserFactory constructor" );
			}
			$mocks[] = $val;
		}

		$factory = new ParserFactory( ...$mocks );
		$parser = $factory->create();

		foreach ( ( new ReflectionObject( $parser ) )->getProperties() as $prop ) {
			$prop->setAccessible( true );
			foreach ( $mocks as $idx => $mock ) {
				if ( $prop->getValue( $parser ) === $mock ) {
					unset( $mocks[$idx] );
				}
			}
		}

		$this->assertCount( 0, $mocks, 'Not all arguments to the ParserFactory constructor were ' .
			'found in Parser member variables' );
	}

	public function provideConstructorArguments() {
		// Create a mock Config object that will satisfy ServiceOptions::__construct
		$mockConfig = $this->createMock( 'Config' );
		$mockConfig->method( 'has' )->willReturn( true );
		$mockConfig->method( 'get' )->willReturn( 'I like otters.' );

		$mocks = [
			[ 'the plural of platypus...' ],
			$this->createMock( 'MagicWordFactory' ),
			$this->createMock( 'Language' ),
			'...is platypodes',
			$this->createMock( 'MediaWiki\Special\SpecialPageFactory' ),
			$mockConfig,
			$this->createMock( 'MediaWiki\Linker\LinkRendererFactory' ),
		];

		yield 'args_without_namespace_info' => [
			$mocks,
		];
		yield 'args_with_namespace_info' => [
			array_merge( $mocks, [ $this->createMock( 'NamespaceInfo' ) ] ),
		];
	}

	/**
	 * @dataProvider provideConstructorArguments
	 * @covers ParserFactory::__construct
	 */
	public function testBackwardsCompatibleConstructorArguments( $args ) {
		$this->hideDeprecated( 'ParserFactory::__construct with Config parameter' );
		$factory = new ParserFactory( ...$args );
		$parser = $factory->create();

		// It is expected that these are not present on the parser.
		unset( $args[5] );
		unset( $args[0] );

		foreach ( ( new ReflectionObject( $parser ) )->getProperties() as $prop ) {
			$prop->setAccessible( true );
			foreach ( $args as $idx => $mockTest ) {
				if ( $prop->getValue( $parser ) === $mockTest ) {
					unset( $args[$idx] );
				}
			}
		}

		$this->assertCount( 0, $args, 'Not all arguments to the ParserFactory constructor were ' .
			'found in Parser member variables' );
	}
}
