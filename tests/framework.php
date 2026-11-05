<?php
/**
 * Minimal test framework for the J1 suite.
 *
 * No dependency, no network: the whole suite runs inside a real WordPress through
 * `wp eval-file`, which is the only way to test a plugin against the real API.
 *
 * @package Portfolio_Studio_J1
 */

/**
 * Tiny TAP-like test runner.
 */
class PS_Test_Runner {

	/**
	 * Number of passed tests.
	 *
	 * @var int
	 */
	public static $passed = 0;

	/**
	 * Number of failed tests.
	 *
	 * @var int
	 */
	public static $failed = 0;

	/**
	 * Failure messages.
	 *
	 * @var array<int,string>
	 */
	public static $failures = array();

	/**
	 * Current test number.
	 *
	 * @var int
	 */
	public static $index = 0;

	/**
	 * Assert that a condition is true.
	 *
	 * @param bool   $condition Condition.
	 * @param string $name      Test name.
	 * @param string $detail    Extra detail printed on failure.
	 * @return bool
	 */
	public static function ok( $condition, $name, $detail = '' ) {
		self::$index++;

		if ( $condition ) {
			self::$passed++;
			printf( "ok %d - %s\n", self::$index, $name );
			return true;
		}

		self::$failed++;
		$message = $name . ( '' !== $detail ? ' :: ' . $detail : '' );
		self::$failures[] = $message;
		printf( "not ok %d - %s\n", self::$index, $message );

		return false;
	}

	/**
	 * Assert strict equality.
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $name     Test name.
	 * @return bool
	 */
	public static function same( $expected, $actual, $name ) {
		return self::ok(
			$expected === $actual,
			$name,
			'expected ' . self::render( $expected ) . ' got ' . self::render( $actual )
		);
	}

	/**
	 * Assert loose equality (used for values read back from the database).
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $name     Test name.
	 * @return bool
	 */
	public static function equals( $expected, $actual, $name ) {
		return self::ok(
			$expected == $actual, // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$name,
			'expected ' . self::render( $expected ) . ' got ' . self::render( $actual )
		);
	}

	/**
	 * Assert that a value is not empty.
	 *
	 * @param mixed  $value Value.
	 * @param string $name  Test name.
	 * @return bool
	 */
	public static function not_empty( $value, $name ) {
		return self::ok( ! empty( $value ), $name, 'value is empty' );
	}

	/**
	 * Render a value for a failure message.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function render( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_null( $value ) ) {
			return 'null';
		}
		if ( is_scalar( $value ) ) {
			return var_export( $value, true );
		}
		if ( is_array( $value ) ) {
			return 'array(' . count( $value ) . ') ' . wp_json_encode( $value );
		}
		if ( is_object( $value ) ) {
			return get_class( $value );
		}

		return gettype( $value );
	}

	/**
	 * Print the summary and return the exit code.
	 *
	 * @return int
	 */
	public static function summary() {
		printf( "1..%d\n", self::$index );
		printf( "# passed %d\n", self::$passed );
		printf( "# failed %d\n", self::$failed );

		foreach ( self::$failures as $failure ) {
			printf( "# FAIL %s\n", $failure );
		}

		return self::$failed > 0 ? 1 : 0;
	}
}
