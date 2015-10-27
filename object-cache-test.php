<?php

function _deprecated_function($a, $b, $c) {}

$blog_id = 1;
$table_prefix = 'wp_';

define( 'DB_NAME', 'db_name' );
define( 'DAY_IN_SECONDS', 600 );

function bool_to_str( $val ) {
    return ( $val ? 'true' : 'false' ) . "\n";
}

require_once 'object-cache.php';


// test wp_cache_init();
wp_cache_init();
global $wp_object_cache;
echo "Cache object exist: " . bool_to_str( $wp_object_cache instanceof WP_Object_Cache );


wp_cache_flush();

// test wp_cache_incr and wp_cache_get

wp_cache_incr('integer', 1, 'test-group');
wp_cache_incr('integer', 3, 'test-group');

$r = wp_cache_get('integer', 'test-group');
$rf = wp_cache_get('integer', 'test-group', true);

echo "wp_cache_incr force: " . bool_to_str( $r == $rf );
echo "wp_cache_incr: " . bool_to_str( $r == 4 );


// test wp_cache_decr

wp_cache_decr('integer', 1, 'test-group');
wp_cache_decr('integer', 3, 'test-group');
wp_cache_decr('integer', 1, 'test-group');

$r = wp_cache_get('integer', 'test-group');
$rf = wp_cache_get('integer', 'test-group', true);

echo "wp_cache_decr force: " . bool_to_str( $r == $rf );
echo "wp_cache_decr: " . bool_to_str( $r == -1 );

// test wp_cache_add

$true = wp_cache_add('key', 'value', 'test-group');
$false = wp_cache_add('integer', 'value', 'test-group');

echo "wp_cache_add: " . bool_to_str( $true === true );
echo "wp_cache_add exist: " . bool_to_str( $false === false );

$r = wp_cache_get('key', 'test-group');
$rf = wp_cache_get('key', 'test-group', true);

echo "wp_cache_add > wp_cache_get force: " . bool_to_str( $r == $rf );
echo "wp_cache_add > wp_cache_get: " . bool_to_str( $r == 'value' );


$r = wp_cache_get('integer', 'test-group');
$rf = wp_cache_get('integer', 'test-group', true);

echo "wp_cache_add exist > wp_cache_get force: " . bool_to_str( $r == $rf );
echo "wp_cache_add exist > wp_cache_get: " . bool_to_str( $r == -1 );

// test wp_cache_delete

wp_cache_delete('key', 'test-group');
wp_cache_delete('integer', 'test-group');
$false = wp_cache_get('key', 'test-group');
$falsef = wp_cache_get('integer', 'test-group', true);

echo "wp_cache_delete > wp_cache_get force:" . bool_to_str( $falsef === $false );
echo "wp_cache_delete > wp_cache_get: " . bool_to_str( $false === false );


// test wp_cache_replace

$false = wp_cache_replace('key', 'value', 'test-group');
wp_cache_set('key', 'value', 'test-group');
$true = wp_cache_replace('key', 'value', 'test-group');

echo "wp_cache_replace: " . bool_to_str( $true === true );
echo "wp_cache_replace no exist: " . bool_to_str( $false == false );

$r = wp_cache_get('key', 'test-group');
$rf = wp_cache_get('key', 'test-group', true);


echo "wp_cache_replace > wp_cache_get force: " . bool_to_str( $r == $rf );
echo "wp_cache_replace > wp_cache_get: " . bool_to_str( $r == 'value' );

// test wp_cache_set

wp_cache_set('a', 'b', 'test-group');

$r = wp_cache_get('a', 'test-group');
$rf = wp_cache_get('a', 'test-group', true);

echo "wp_cache_set > wp_cache_get force: " . bool_to_str( $r == $rf );
echo "wp_cache_set > wp_cache_get: " . bool_to_str( $r =='b');