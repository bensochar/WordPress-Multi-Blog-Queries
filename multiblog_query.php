<?php

// Attempt to catch get_permalink() calls
add_filter( 'post_link', 'Multiblog_Query::filter_get_permalink', 0, 2 );


/**
 * Queries other blogs from the same WordPress installation and returns a combined WP_Query object.
 *
 * If a reference to an existing WP_Query object is passed, the query results are added to the existing object,
 * and Multiblog_Query->query points to the existing object. Otherwise, a new WP_Query object is created.
 *
 * @param array $queries - An associative array of queries performed in the format blod_id => query_vars_array | query_string
 * @param array $shared_query_vars - An associative array of query_vars applied to all queries
 * @param WP_Query $query - A WP_Query containing the combined query results
 *
 * @example The following example will merge posts in the category with ID 3 in the blog with ID 1 and posts from the
 * category with ID 5 in the blog with ID 3 with the posts in the current request's automatically generated WP_Query.
 *
 * $query_array = array(
 *      '1' => array( 'cat' => 3 ),
 *      '3' => array( 'cat' => 5 )
 * );
 *
 * $multiblog_query = new Multiblog_Query( $query_array, array( 'post_type' => 'post' ), $wp_query );
 *
 * @author Dagan Henderson <dagan@digitalconversations.tv>
 * @version 1.0.0
 */
class Multiblog_Query {

    public $queries;
    public $query;
    public $shared_query_vars;

    private $_orderby;
    private $_order;

    /**
     * @param array $queries An associative array of blog IDs and queries in the format 'blog_id' => query_vars_array.
     * @param array $joint_query_vars (Optional) An array of WP_Query query_vars to apply to all queries
     * @param WP_Query $query (Optional) A reference to a WP_Query object with which to merge the additional queries
     * @param array $args (Optional) An array of arguments, including:
     *
     * <string> 'thumbnail_size' A valid WordPress image size keyword, a 2-item array (w,h) of size in pixels,
     * or false. If false, no thumbnails will be gathered. If you plan to use thumbnails, do NOT set this to false. If you
     * will not busing thumnbails at all, setting this to false may decrease page load times.
     *
     * <mixed> 'meta_data' Either a string (or one-dimensinional array of strings) of post meta key names
     * to retrieve during the query. The default value is boolean FALSE, which returns no meta data. If meta data will be
     * used while rendering the query results, including the required meta keys in the initial query is far more effiicient.
     *
     * <string> 'ext_blog_note' A string to set to $post->blog_intro for use in the lop.
     */
    public function __construct( array $queries, $joint_query_vars = array(), $query = null, $args = array() ){

        $default_args = array(
            'thumbnail_size' => 'post-thumbnail',
            'meta_data' => false,
            'ext_blog_note' => 'Published in '
            );

        $args = ( is_array( $args ) ) ? array_merge( $default_args, $args ) : $default_args;

        extract( array( 'thumbnail_size' => $args['thumbnail_size'], 'meta_data' => $args['meta_data' ], 'ext_blog_note' => $args['ext_blog_note'] ) );


        $thumbnail_size = ( $thumbnail_size == 'post-thumbnail' ) ? apply_filters( 'post_thumbnail_size', $thumbnail_size ) : $thumbnail_size;

        // Set joint query parameters

        $default_query_vars = array(
            'orderby' => ( get_class( $query ) == 'WP_Query' && array_key_exists( 'orderby' , $query->query_vars ) ) ? $query->query_vars['orderby'] : 'date',
            'order' => ( get_class( $query ) == 'WP_Query' && array_key_exists( 'order' , $query->query_vars ) && $query->query_vars['order'] == 'ASC' || $query->query_vars['order'] == 'DESC' ) ? $query->query_vars['order'] : 'DESC',
            'posts_per_page' => ( get_class( $query ) == 'WP_Query' && array_key_exists( 'posts_per_page' , $query->query_vars ) && $query->query_vars['posts_per_page'] >= 1 ) ? $query->query_vars['posts_per_page'] : 10,
            'paged' => ( get_class( $query ) == 'WP_Query' && array_key_exists( 'paged' , $query->query_vars ) && $query->query_vars['paged'] >= 1 ) ? $query->query_vars['paged'] : 1
        );

        $joint_query_vars = array_merge( $default_query_vars, $joint_query_vars );


        // Set object parameters

        $this->shared_query_vars = $joint_query_vars;

        $this->_orderby = $joint_query_vars['orderby'];

        $this->_order = $joint_query_vars['order'];


        // Adjust posts_per_page to ensure no lost posts on secondary pages due to sorting

        $joint_query_vars['posts_per_page'] = $joint_query_vars['posts_per_page'] * $joint_query_vars['paged'];


        // Build an array of WP_Query objects

        $query_objects = array();

        foreach ( $queries as $blog_id => $query_vars ) {

            if ( switch_to_blog( (int)$blog_id ) ) {

                $query_objects[(string)$blog_id] = new WP_Query( array_merge( $query_vars, $joint_query_vars ) );


                // Add a permalink property to each post for later reference

                foreach ( $query_objects[(string)$blog_id]->posts as $post ) {

                    $post->blog_id = $blog_id;
                    $post->blog_title = get_bloginfo( 'name' );
                    $post->blog_url = get_bloginfo( 'wpurl' );
                    $post->blog_intro = $ext_blog_intro;
                    $post->post_permalink = get_permalink( $post->ID );
                    $post->has_thumbnail = ( false !== $thumbnail_size ) ? has_post_thumbnail( $post->ID ) : null;

                    if ( true === $post->has_thumbnail ) {

                        $thumbnail = get_post_thumbnail_id( $post->ID );

                        $post->post_thumbnail_id = $thumbnail->ID;
                        $post->post_thumbnail_src = wp_get_attachment_image_src( $thumbnail_id, apply_filters( 'post_thumbnail_size', $thumbnail_size ), false );
                        $post->post_thumbnail_size = $thumbnail_size;
                        $post->post_thumbnail_alt = trim( strip_tags( get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ) );
                        $post->post_thumbnail_title = trim( strip_tags( $thumbnail->post_title ) );

                        if ( empty( $post->post_thumbnail_alt ) )
                                $post->post_thumbnail_alt = trim( strip_tags( $thumbnail->post_excerpt ) );

                        if ( empty( $post->post_thumbnail_alt ) )
                                $post->post_thumbnail_alt = trim( strip_tags( $thumbnail->post_title ) );

                        unset( $thumbnail );

                    } else {

                        $post->post_thumbnail_id = 0;
                        $post->post_thumbnail_src = '';
                        $post->post_thumbnail_size = '';
                        $post->post_thumbnail_alt = '';
                        $post->post_thumbnail_title = '';
                    }

                    if ( ( is_array( $meta_data ) || is_string( $meta_data ) ) && !empty( $meta_data ) ) {

                        $post->has_post_meta = true;
                        $post->post_meta = array();

                        if ( is_string( $meta_data ) )
                                $meta_data = array( $meta_data );

                        foreach ( $meta_data as $meta_key ) {

                            if ( !is_string( $meta_key ) ) continue;

                            $post->post_meta[ $meta_key ] = get_post_meta( $post->ID, $meta_key, false );
                        }
                    }

                    unset( $post );
                }
            }

            unset( $blog_id, $query_vars );
        }

        self::restore_current_blog();


        // Build the joint query object

        if ( get_class( $query ) == 'WP_Query' ) {

            $this->_wp_query = $query;


            // Rebuild the query results to prevent lost posts on secondary pages

            if ( $query->is_paged() ) {

                $original_posts_per_page = $query->query_vars['posts_per_page'];

                $query->query_vars['posts_per_page'] = $query->query_vars['posts_per_page'] * $query->query_vars['paged'];

                $query->query_vars_changed = true;

                $query->get_posts();

                $query->query_vars['posts_per_page'] = $original_posts_per_page;

                $this->query_vars_changed = false;
            }

        } else {

            $this->_wp_query = new WP_Query();

            $this->_wp_query->init();
        }

        $this->_wp_query->query_vars = $joint_query_vars;

        foreach ( $query_objects as $query ){

            foreach ( $query->posts as $post ) {

                array_push( $this->_wp_query->posts, $post );
            }
        }

        usort( $this->_wp_query->posts, array( $this, 'sort_posts' ) );


        // Trim the now-sorted posts to the correct offset and length

        $posts_per_page = $this->shared_query_vars['posts_per_page'];

        $page = $this->shared_query_vars['paged'];

        $this->_wp_query->posts = array_slice( $this->_wp_query->posts, ( $page - 1 ) * $posts_per_page, $posts_per_page );


        // Update the post count

        $this->_wp_query->post_count = count( $this->_wp_query->posts );
    }

    protected function sort_posts( $a, $b ) {

        $order = strtoupper( $this->_order );

        $order = ( $order == 'ASC' || $order == 'DESC' ) ? $order : 'DESC';

        $orderby_map = array(
            'none' => 'none',
            'id' => 'ID',
            'author' => 'post_author',
            'title' => 'post_title',
            'date' => 'post_date',
            'modified' => 'post_modified',
            'parent' => 'post_parent',
            'rand' => 'random',
            'comment_count' => 'comment_count',
            'menu_order' => 'menu_order'
        );

        $orderby = strtolower( $this->_orderby);

        $orderby = array_key_exists( $orderby , $orderby_map ) ? $orderby_map[$orderby] : 'post_date';


        // Execute orderby logic

        switch( $orderby ) {

            // Don't sort at all

            case 'none' : return 0;


            // Interger sort

            case 'ID' :
            case 'post_parent' :
            case 'menu_order' :

                if ( $a->$orderby == $b->orderby ) return 0;

                if ( $order == 'DESC' ) {

                    return ( $a->$orderby < $b->$orderby ) ? 1 : -1;

                } else {

                    return ( $a->$orderby > $b->$orderby ) ? 1 : -1;
                }


            // String sort

            case 'post_author' :
            case 'post_title' :
            case 'post_date' :
            case 'post_modifed' :
            case 'comment_count' :

                if ( $a->$orderby == $b->$orderby ) return 0;

                if ( $order == 'DESC' ) {

                    return strcmp( $b->$orderby, $a->$orderby );

                } else {

                    return strcmp( $a->$orderby, $b->$orderby );
                }


            // Random sort

            case 'random' : return mt_rand( -1, 1 );
        }
    }


    /*
     * Static Functions
     */


    /**
     * Filters get_permalink() requests in multiblog queries
     *
     * Ensures the proper permalink is returned for posts retrieved from
     * other blogs using the Multiblog_Queries class.
     *
     * @param string $permalink The unfiltered permalink
     * @param stdObject $post The post object
     * @return string The filtered permalink
     */
    static function filter_get_permalink( $permalink, $post ){

        if ( isset( $post->post_permalink ) && null != $post->post_permalink ) {

            return $post->post_permalink;

        } elseif ( isset( $post->blog_id ) && is_int( $post->blog_id ) ) {

            if ( switch_to_blog( $post->blog_id ) ) {

                $post->post_permalink = get_permalink( $post->ID );

                self::restore_current_blog();

                return $post->post_permalink;

            } else {

                return $permalink;
            }

        } else {

            return $permalink;
        }
    }


    /**
     * An alias for WordPress's native get_permalink()
     *
     * @return string If in the loop, the current post's permalink. If not,
     * an empty string.
     */
    static function get_permalink(){

        if ( in_the_loop() ) return get_permalink();

        return '';
    }

    /**
     * An alias for WordPress's native the_permalink()
     */
    static function the_permalink() {

        if ( in_the_loop() ) echo get_permalink();

        return;
    }


    /**
     * A Multiblog_Query alternative to WordPress's native the_ID()
     *
     * If in the loop and the current $post is from an alternate blog,
     * writes the blog ID and post ID to the output buffer in the format
     * "blog_ID-post_ID". For example, if the $post is from a blog with
     * the ID '2' and the $post ID is '153', it writes 2-153.
     *
     * If the $post is from the current blog, just the post ID is written.
     *
     * @global stdObject $post
     */
    static function the_Id() {

        global $post;

        if ( in_the_loop() ) {

            $id = ( isset( $post->blog_id ) ) ? "{$post->blog_id}-{$post->ID}" : "{$post->ID}";

            echo $id;
        }
    }


    /**
     * A Multiblog_Query alternative to WordPress's native get_post_thumbnail_id()
     *
     * Should be use din place of the native WordPress function when checking Multblog
     * Query object.
     *
     * @param stdObject $post A WordPress $post object.
     * @return int The attachment ID of the $post's thumbnail
     */
    static function get_post_thumbnail_id( $post = null ) {

        if ( is_null( $post ) ){

            unset( $post);

            global $post;
        }



        if ( is_int( $post ) ) return get_post_thumbnail_id( $post );

        if ( !is_object( $post ) ) return 0;

        if ( isset( $post->post_thumbnail_id ) && is_int( $post->post_thumbnail_id ) ) return $post->post_thumbnail_id;

        if ( !isset( $post->blog_id ) ) return get_post_thumbnail_id( $post->ID );


        if ( self::has_post_thumbnail( $post ) ) {

            return $post->post_thumbnail_id;

        } else {

            return 0;
        }
    }


    /**
     * A Multiblog_Query alternative to WordPress's native has_post_thumbnail
     *
     * Should be used in place of the native WordPress function when checking Multiblog
     * Query objects.
     *
     * @global stdObject $post The current $post, if accessible.
     * @param stdObject $post A WordPress $post object to check for a thumbnail.
     * @return bool True if a post thumbnail is available. False if no thumbnail is found.
     */
    static function has_post_thumbnail( $post = null ) {

        if ( is_null( $post ) ) {

            unset( $post );

            global $post;
        }


        if ( isset( $post->has_thumbnail ) && is_bool( $post->has_thumbnail ) ) return $post->has_thumbnail;


        if ( is_int( $post ) ) return has_post_thumbnail( $post );

        if ( !is_object( $post ) ) return false;


        if ( isset( $post->blog_id ) && is_int( $post->blog_id ) ) {

            if ( switch_to_blog( $post->blog_id, true ) ) {

                if ( has_post_thumbnail( $post->ID ) ){

                    $post->has_thumbnail = true;
                    $post->post_thumbnail_id = get_post_thumbnail_id( $post->ID );

                } else {

                    $post->has_thumbnail = false;
                }

                self::restore_current_blog();

                return $post->has_thumbnail;

            } else {

                return false;
            }

        } else {

            return has_post_thumbnail( $post->ID );
        }
    }


    /**
     * A Multiblog_Query alternative to WordPress's the_post_thumbnail()
     *
     * Writes an <img> element of the current post's thumbnail to the output
     * buffer.
     *
     * @param mixed $size Either a valid WordPress image size keyword or a 2-item integer
     * array in the format ( width_in_pixels, height_in_pixels ). If post thumbnails were
     * retrieved during the initial Multiblog_Query, this value is ignored.
     * @param type $attr
     */
    static function the_post_thumbnail( $size= 'post-thumbnail', $attr = '' ){

        echo self::get_the_post_thumbnail( null, $size, $attr );
    }


    /**
     * A Multiblog_Query alternative to WordPress's get_the_post_thumbnail()
     *
     * Returns an HTML <img> element string of the $post's thumbnail.
     *
     * Note: If $thumbnail_size in the Multiblog_Query's instantiation is either a
     * different size than in this function call or if it is set to false, this function
     * may be quite slow. For maximum efficiency, gather the proper $thumbnail_size
     * while instantiating Multiblog_Query.
     *
     * @param stdObject The post object for which to return the thumbnail. If null, the
     * Global $post value will be used.
     * @param mixed $size Either a valid WordPress image size keyword or a 2-item integer
     * array in the format ( width_in_pixels, height_in_pixels ). If post thumbnails were
     * retrieved during the initial Multiblog_Query, this value is ignored.
     * @param array $attr An array of attributes to be added to the <img> element in
     * the format attribute_nume => attribute_value;
     */
    static function get_the_post_thumbnail( $post = null, $size = '',$attr = '' ){

        if ( is_null( $post ) ){

            unset( $post );

            global $post;
        }


        if ( is_int( $post ) ) return get_the_post_thumbnail( $post, $size, $attr );


        if ( isset( $post->has_thumbnail ) && is_bool( $post->has_thumbnail ) ) {

            if ( $post->has_thumbnail && isset( $post->post_thumnbail_src ) ) {

                if ( isset( $post->post_thumbnail_size ) && is_array( $post->post_thumbnail_size ) )
                        join( 'x', $post->post_thumbnail_size );

                if ( !isset( $post->post_thumbnail_size ) || empty( $post->post_thumbnail_size ) )
                        $post->post_thumbnail_size = 'post-thumbnail';

                $default_attr = array();

                $default_attr['class'] = "attachment-{$post->post_thumbnail_size}";
                $default_attr['title'] = isset( $post->post_thumbnail_title ) ? $post->post_thumbnail_title : '';
                $default_attr['alt'] = isset( $post->post_thumbnail_alt ) ? $post->post_thumbnail_alt : '';

                $attr = wp_parse_args( $attr, $default_attr );

                $attr = array_map( 'esc_attr', $attr );

                list( $src, $width, $height ) = $post->post_thumbnail_src;

                $html = "<img src=\"$src\" width=\"$width\$ height=\"$height\" ";

                foreach ( $attr as $name => $value ) {

                    $html .= "$name=\"$value\" ";
                }

                $html .= '/>';

                return $html;

            } else {

                return '';
            }

        } elseif ( isset( $post->blog_id ) && is_int( $post->blog_id ) && switch_to_blog( $post->blog_id )  ) {

            $thumbnail_id = self::get_post_thumbnail_id( $post );

            $html = wp_get_attachment_image( $thumbnail_id, apply_filters( 'post_thumbnail_size', $size ), false, $attr );

            self::restore_current_blog();

            return $html;

        } else {

            return get_the_post_thumbnail( $post->ID, $size, $attr );
        }
    }


    /**
     * A Multiblog_Query alternative to WordPress's native get_post_meta().
     *
     * Retrieves post metadata. If metadata is used in the loop, the relevant
     * meta keys should be passed in the $meta_data parameter when instantiating
     * a Multiblgo_Query. Failing to gather the meta data during instantiation
     * will greatly deminish efficiency and increase page load times.
     *
     * @param stdObject $post
     * @param string $key The metadata key for which to return a value(s).
     * @param Boolean $single If true, only the first value with the matching meta
     * key will be returned. If that value is serialized, it will be unserialzed
     * prior to being returned. If false (default), an array of meta values will
     * be returned. If there is only one matching value, a single-item array will
     * be returned. Serialized values within the array will be left untouched and
     * must be unserialized after being returned.
     * @return mixed If $single is true, the first matching meta value is returned.
     * If that value was serialized, it is unserialized. If $single is false, an
     * array of matching meta values is returned, with serialized values left
     * untouched.
     */
    static function get_post_meta( $post, $key, $single = false ) {

        if ( null === $post ) {

            unset( $post );

            global $post;
        }

        if ( is_int( $post ) ) return get_post_meta( $post, $key, $single );

        if ( !is_object( $post ) ) return false;


        if ( isset( $post->has_post_meta ) && is_bool( $post->has_post_meta ) ) {

            if ( is_array( $post->post_meta ) && array_key_exists( $key, $post->post_meta ) ) {

                if ( !array_key_exists( $key, $post->post_meta ) ) return false;

                if ( $single ) {

                    $unserialized_data = @unserialize( $post->post_meta[$key][0] );

                    return ( false !== $unserialized_data && $post->post_meta[$key][0] !== 'b:0;' ) ? $unserialized_data : $post->post_meta[$key][0];

                } else {

                    return $post->post_meta[$key];
                }

            } else {

                return false;
            }

        } elseif ( isset( $post->ID ) && is_int( $post->ID ) && isset( $post->blog_id ) && is_int( $post->blog_id ) && switch_to_blog( $post->blog_id ) ) {

            $post_meta = get_post_meta( $post->ID, $key, $single );

            self::restore_current_blog();

            return $post_meta;

        } elseif ( isset( $post->ID ) && is_int( $post->ID ) ) {

            return get_post_meta( $post->ID, $key, $single );

        } else {

            return false;
        }
    }


    /**
    * Switches to the original blog
    *
    * Similar to WordPress Core's restore_current_blog(), but returns to the top of
    * the switch stack instead one step back.
    */
    static function restore_current_blog(){

        global $switched, $switched_stack;

        // Return false if we're not switched
        if ( !$switched ) return false;

        // Reduce the switched stack to one
        $switched_stack = array( array_shift( $switched_stack ) );

        // Let core do the heavy lifting
        return restore_current_blog();
    }

} ?>