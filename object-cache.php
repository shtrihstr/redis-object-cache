<?php

defined( 'WP_REDIS_HOST' ) || define( 'WP_REDIS_HOST', '127.0.0.1' );
defined( 'WP_REDIS_PORT' ) || define( 'WP_REDIS_PORT', 6379 );
defined( 'WP_REDIS_TIMEOUT' ) || define( 'WP_REDIS_TIMEOUT', 2 );

function wp_cache_add($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->add($key, $data, $group, $expire);
}

function wp_cache_incr($key, $n = 1, $group = '') {
    global $wp_object_cache;
    return $wp_object_cache->incr($key, $n, $group);
}

function wp_cache_decr($key, $n = 1, $group = '') {
    global $wp_object_cache;
    return $wp_object_cache->decr($key, $n, $group);
}

function wp_cache_close() {
    global $wp_object_cache;
    return $wp_object_cache->close();
}

function wp_cache_delete($key, $group = '') {
    global $wp_object_cache;
    return $wp_object_cache->delete($key, $group);
}

function wp_cache_flush() {
    global $wp_object_cache;
    return $wp_object_cache->flush();
}

function wp_cache_get($key, $group = '', $force = false) {
    global $wp_object_cache;
    return $wp_object_cache->get($key, $group, $force);
}

function wp_cache_init() {
    global $wp_object_cache;
    $wp_object_cache = new WP_Object_Cache();
}

function wp_cache_replace($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->replace($key, $data, $group, $expire);
}

function wp_cache_set($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    if ( defined( 'WP_INSTALLING' ) == false ) {
        return $wp_object_cache->set( $key, $data, $group, $expire );
    } else {
        return $wp_object_cache->delete( $key, $group );
    }
}

function wp_cache_switch_to_blog( $blog_id ) {
    global $wp_object_cache;
    return $wp_object_cache->switch_to_blog( $blog_id );
}

function wp_cache_add_global_groups( $groups ) {
    global $wp_object_cache;
    $wp_object_cache->add_global_groups($groups);
}

function wp_cache_add_non_persistent_groups( $groups ) {
    global $wp_object_cache;
    $wp_object_cache->add_non_persistent_groups($groups);
}

class WP_Object_Cache
{
    private $_max_expire = DAY_IN_SECONDS;
    private $_prefix = '';
    private $_cache = [];

    /**
     * @var Redis
     */
    private $_redis = null;
    private $_connected = false;

    private $_no_redis_groups = [];
    private $_global_groups = [];
    private $_stats = ['hit' => 0, 'miss' => 0, 'requests' => []];
    private $_blog_id;

    public function __construct()
    {
        global $blog_id, $table_prefix;
        $this->_blog_id = $blog_id;
        $this->_prefix = DB_NAME . ':' . $table_prefix . ':obj:';

        if( class_exists( 'Redis' ) ) {
            try {
                $this->_redis = new Redis();
                if( $this->_connected = $this->_redis->connect( WP_REDIS_HOST, WP_REDIS_PORT, WP_REDIS_TIMEOUT ) ) {
                    $this->_redis->setOption( Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP );
                }
            }
            catch(Exception $e) {
                $this->_connected = false;
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function _get_redis_key( $key, $group )
    {
        return $this->_get_prefix( $group ) . ':' . $group . ':' . $key;
    }

    private function _get_prefix( $group )
    {
        $id = in_array( $group, $this->_global_groups ) ? 0 : $this->_blog_id;
        return $this->_prefix . $id;
    }

    public function set( $key, $data, $group = 'default', $expire = 0 )
    {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        if( empty( $expire ) || $expire < 0 ) {
            $expire = $this->_max_expire;
        }

        if ( is_object( $data ) ) {
            $data = clone $data;
        }

        if( $this->_connected && ! in_array( $group, $this->_no_redis_groups ) ) {

            if( defined( 'SAVEQUERIES' ) ) {
                $time = microtime( true );
            }

            try {
                $this->_redis->set( $this->_get_redis_key( $key, $group ), $data, $expire );
            }
            catch( Exception $e ) {
                $this->_connected = false;
            }

            if( defined( 'SAVEQUERIES' ) ) {
                isset( $this->_stats['requests'][ $group ]['set'] ) ? $this->_stats['requests'][ $group ]['set']++ : $this->_stats['requests'][ $group ]['set'] = 1;
                isset( $this->_stats['requests'][ $group ]['set_time'] ) ?  $this->_stats['requests'][ $group ]['set_time'] += microtime( true ) - $time : $this->_stats['requests'][ $group ]['set_time'] = microtime( true ) - $time;
            }
        }

        $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] = $data;
        return true;
    }

    public function get( $key, $group = 'default', $force = false, &$found = null )
    {
        $found = false;
        if ( empty( $group ) ) {
            $group = 'default';
        }

        if ( ! $force && $this->_exists( $key, $group ) ) {
            $found = true;
            $this->_stats['hit']++;
            if ( is_object( $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] ) ) {
                return clone $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ];
            }
            else {
                return $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ];
            }
        }

        if( $this->_connected && ! in_array( $group, $this->_no_redis_groups ) ) {

            if( defined( 'SAVEQUERIES' ) ) {
                $time = microtime( true );
            }

            try {
                $data = $this->_redis->get( $this->_get_redis_key( $key, $group ) );
            }
            catch( Exception $e ) {
                $this->_connected = false;
                return false;
            }


            if( defined( 'SAVEQUERIES' ) ) {
                isset( $this->_stats['requests'][ $group ]['get'] ) ? $this->_stats['requests'][ $group ]['get']++ : $this->_stats['requests'][ $group ]['get'] = 1;
                isset( $this->_stats['requests'][ $group ]['get_time'] ) ?  $this->_stats['requests'][ $group ]['get_time'] += microtime( true ) - $time : $this->_stats['requests'][ $group ]['get_time'] = microtime( true ) - $time;
            }

            if( false !== $data ) {
                $found = true;
                $this->_stats['hit']++;
                $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] = $data;
                if ( is_object( $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] ) ) {
                    return clone $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ];
                }
                else {
                    return $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ];
                }
            }
            else {
                $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] = false;
            }
        }
        $this->_stats['miss']++;
        return false;
    }

    private function _exists( $key, $group )
    {
        return isset( $this->_cache[ $this->_get_prefix( $group ) ][ $group ] ) && isset( $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] );
    }

    public function delete( $key, $group = 'default', $deprecated = false )
    {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        if ( $this->_exists( $key, $group ) ) {
            unset( $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] );
        }

        if( $this->_connected && ! in_array( $group, $this->_no_redis_groups ) ) {
            try {
                $this->_redis->del( $this->_get_redis_key( $key, $group ) );
            }
            catch( Exception $e ) {
                $this->_connected = false;
                return false;
            }
        }

        return true;
    }

    public function flush()
    {
        $this->_cache = [];

        if( $this->_connected ) {

            try {
                $keys = $this->_redis->keys( $this->_prefix . $this->_blog_id . ':*' );
                foreach ( $keys as $key ) {
                    $this->_redis->del( $key );
                }

                $keys = $this->_redis->keys( $this->_prefix . '0:*' );
                foreach ( $keys as $key ) {
                    $this->_redis->del( $key );
                }
            }
            catch( Exception $e ) {
                $this->_connected = false;
                return false;
            }

        }
        return true;
    }

    public function add( $key, $data, $group = 'default', $expire = 0 )
    {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        if( $this->_connected && ! in_array( $group, $this->_no_redis_groups ) ) {
            try {
                if( ! $this->_redis->exists( $this->_get_redis_key( $key, $group ) ) ) {
                    return $this->set( $key, $data, $group, $expire );
                }
            }
            catch( Exception $e ) {
                $this->_connected = false;
                return false;
            }
        }
        elseif( ! $this->_exists( $key, $group ) ) {
            return $this->set( $key, $data, $group, $expire );
        }

        return false;
    }

    public function replace( $key, $data, $group = 'default', $expire = 0 )
    {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        if( $this->_connected && ! in_array( $group, $this->_no_redis_groups ) ) {
            try {
                if( $this->_redis->exists( $this->_get_redis_key( $key, $group ) ) ) {
                    return $this->set( $key, $data, $group, $expire );
                }
            }
            catch( Exception $e ) {
                $this->_connected = false;
                return false;
            }
        }
        elseif( $this->_exists( $key, $group ) ) {
            return $this->set( $key, $data, $group, $expire );
        }

        return false;
    }

    public function incr( $key, $offset = 1, $group = 'default' )
    {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        if( $this->_connected && ! in_array( $group, $this->_no_redis_groups ) ) {
            try {
                $this->_redis->incrBy( $this->_get_redis_key( $key, $group ), $offset );
            }
            catch( Exception $e ) {
                $this->_connected = false;
                return false;
            }

            if( ! $this->_exists( $key, $group ) ) {
                $this->get( $key, $group );
            }
            else {
                $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] += $offset;
            }
        }
        else {
            if( ! $this->_exists( $key, $group ) ) {
                $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] = 0;
            }
            $this->_cache[ $this->_get_prefix( $group )][ $group ][ $key ] += $offset;
        }

        return true;
    }

    public function decr( $key, $offset = 1, $group = 'default' )
    {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        if( $this->_connected && ! in_array( $group, $this->_no_redis_groups ) ) {
            try {
                $this->_redis->decrBy( $this->_get_redis_key( $key, $group ), $offset );
            }
            catch( Exception $e ) {
                $this->_connected = false;
                return false;
            }
            if( ! $this->_exists( $key, $group ) ) {
                $this->get( $key, $group );
            }
            else {
                $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] -= $offset;
            }
        }
        else {
            if( ! $this->_exists( $key, $group ) ) {
                $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] = 0;
            }
            $this->_cache[ $this->_get_prefix( $group ) ][ $group ][ $key ] -= $offset;
        }

        return true;
    }

    public function reset()
    {
        _deprecated_function( __FUNCTION__, '3.5', 'switch_to_blog()' );
        $this->_cache = [];
    }

    public function stats()
    {
        $time = 0;
        foreach ( array_keys( $this->_cache[ $this->_get_prefix( 'all' ) ] ) as $group ) {
            $time +=  @$this->_stats['requests'][$group]['get_time'] + @$this->_stats['requests'][$group]['set_time'];
        }
        $time = sprintf( '%.2f', $time * 1000 );
        $status = $this->_connected  ? 'Ok' : 'Not connected';
        echo "<p>";
        echo "<strong>Status:</strong> {$status}<br />";
        echo "<strong>Cache Hits:</strong> {$this->_stats['hit']}<br />";
        echo "<strong>Cache Misses:</strong> {$this->_stats['miss']}<br />";
        echo "<strong>Total Time:</strong> $time ms<br />";
        echo "</p>";
        echo '<table style="width: 100%">';
        echo '<tr><th>Group</th><th>Set</th><th>Get</th><th>Set time</th><th>Get time</th></tr>';
        foreach ( array_keys( $this->_cache[ $this->_get_prefix( 'all' ) ] ) as $group ) {
            $get = absint( @$this->_stats['requests'][$group]['get'] );
            $get_time = sprintf( '%.2f', @$this->_stats['requests'][$group]['get_time'] * 1000 );
            $set = absint( @$this->_stats['requests'][$group]['set'] );
            $set_time = sprintf( '%.2f', @$this->_stats['requests'][$group]['set_time'] * 1000 );
            echo '<tr>';
            echo "<th>$group</th>";
            echo "<th>$set</th>";
            echo "<th>$get</th>";
            echo "<th>$set_time ms</th>";
            echo "<th>$get_time ms</th>";
            echo '</tr>';
        }
        echo '</table>';
    }

    public function switch_to_blog( $blog_id )
    {
        $this->_blog_id = $blog_id;
    }

    public function add_non_persistent_groups( $groups )
    {
        if ( ! is_array( $groups ) ) {
            $groups = (array) $groups;
        }
        $this->_no_redis_groups = array_unique( array_merge( $this->_no_redis_groups, $groups ) );
    }

    public function add_global_groups( $groups )
    {
        if ( ! is_array( $groups ) ) {
            $groups = (array) $groups;
        }
        $this->_global_groups = array_unique( array_merge( $this->_global_groups, $groups ) );
    }

    public function close()
    {
        if( $this->_connected ) {
            try {
                $this->_redis->close();
            }
            catch( Exception $e ) { }
            $this->_connected = false;
        }
    }
}

