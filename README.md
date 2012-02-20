# WordPress Multi-Blog Query #

**Version:** 1.1.0

This class offers a mechanism for retrieving posts from WordPress blogs in the same multisite installation and incorprating them into the current site's index and/or archives.

## Release Notes ##

This is the first beta release of this class. Please test it thoroughly before using in production. If bugs are found, please report them using the GitHub Issues tool. I promise to follow up quickly.

## Object Instantiation ##

The class constructor accepts four parameters:

 - an array of query arrays
 - an optional array of shared query variables
 - a valid WP_Query object to merge with
 - an optional array of arguments, including:
  - thumbnail_size
  - meta\_data
  - ext\_blog\_note

The first parameter is the only one that is required, and is a multidemensional associative array. The targeted blog's ID is the key (as a string) and the value is an array of WordPress query variables.

If not overridden by specifying order, orderby and/or posts\_per\_page, the default query returns 10 posts in descending order by date published.

### Examples ###

#### Basic Example ####

```php
$multiblog_query = new Multiblog_Query( array(
	'3' => array( 'author' => 3 ),
	'7' => array( 'cat' => 5 )
) );
```

The preceding code example will retrieve all posts in blog ID 3 written by the author with ID 3 and all posts from the blog ID 7 in the category with ID 5.

For a list of valid query variables, visit http://codex.wordpress.org/Class\_Reference/WP\_Query#Parameters.

#### Advanced Example ####

```php
global $wp_query;

$multiblog_query = new Multiblog_Query(
	array( '5' => array( 'post_type' => 'product' ) ), // Get all 'posts' from Blog ID 5
	array( 'order' => 'ASC', 'orderby' => 'menu_order' ), // Sort them by ascending Menu Order
	$wp_query, // merge the returned posts with the default WP_Query object
	array(
		'thumbnail_size' => 'post-thumbnail',
		'meta_data' => array( 'popularity', 'ratings' ), // gather 'popularity' and 'ratings' post meta data
		'ext_blog_intro' => 'Published on: '
	)
);
```

The preceding example returns the 10 'product' posts from blog ID 5 with the lowest menu_order values, and also retreives featured images in thumbnail size and 'popularity' and 'ratings' metadata. Each post in the query
retrieved from an external blog will have its 'blog_intro' property set to 'Published on: '. In the loop,

```php echo $post->blog_intro . $post->title; ```

can be used to declare that the post is actually published on another blog.

## Working With Multiblog\_Query Objects in the Loop ##

To mirror native WordPress functionality, the Multiblog\_Query class includes a handful of static methods for handling:

 - permalinks
 - thumbnails
 - meta data
 - post IDs

Each method *must* be called using the scope-resolution operator (Paamayim Nekudotayim). For example, Multiblog\_Query:_the\_permalink\();. Failing to include the class name will call the native WordPress function, which will return innaccurate results for posts retrieved from alternate blogs.

### Permalinks ###

WordPress's native permalink functions, the\_permalink() and get\_permalink(), rely on the post ID and current blog's permalink rules to generate valid permalinks. Meaning posts from alternate blogs will be assigned invalid permalinks by these functions.

The Multiblog\_Query class offers two mechanisms for addressing this issue: a permalink filter and an alternative the_permalink() function.

#### Permalink Filtering ####

The first line of the Multiblog\_Query class file adds a filter to the 'post_link' hook. This fitler is run whenever get\_permalink() is executed against a post, and if that post is from an alternate blog, the filter applies the correct permalink, overriding the permalink determined by WordPress.

#### Direct Permalink Access ####

There are two options to skip the filtering mechanism altogeter:

 - execute Multiblog\_Query::_the\_permalink_() from within the loop to write the current post's permalink to the output buffer.
 - acess $post->post_permalink, which is set on all posts gathered from alternate blogs

The first option is a good choice when executing within the loop, since it returns the correct permalink even if the post is from the current blog.

### Thumbnails ###

Multiblog\_Query offers four methods for interacting with thumbnails:

 - has\_post\_thumbnail()
 - the\_post\_thumbnail()
 - get\_the\_post_thumbnail()
 - get\_post\_thumbnail\_id()

All of the thumbnail methods gracefully handle posts from the current blog and alternate blogs, and as such, they should be used in place of their native counterparts whenever Multiblgo_Query is used.

In all cases, the thumbnail method work regardless of whether or not thumbnails were retrieved during instantiation.  However, accessing thumbnails not gathered during instantiation is far from efficient. So it is *always* best to retireve thumbnails during instantiation.

#### has\_post\_thumbnail() ####

This method accepts a single parameter, a valid WordPress post object. If no parameter is passed, the current Global $post value is used in stead.

If a post thumbnail is set, the method returns true. Otherwise it returns false.

**Note:** If Multiblog\_Query is instantiated with $thumbnail_size set to **false**, this method will use WordPress's swith_to_blog() function to check the post for a thumbnail, which can be expensive.

#### the\_post\_thumbnail() ####

Calls Multiblog\_Query::_get\_the\_post\_thumbnail_() and echoes the result, but must be called from within the loop.

Optionally accespts a $size and $attribute parameter, in that order. If $size is not equal to the $thumbnail_size parameter passed when instantiating Multiblog\_Query, WordPress's switch_to_blog() function is used to retrieve the requested size, and that can be expensive.

#### get\_the\_post\_thumbnail() ####

Accepts three parameters:

 - a WordPress $post object (if in the loop, this parameter can be omitted or passed as null)
 - (optional) a WordPress attachment size keyword or array of integers in the format array( width_in_pixels, height_in_pixels )
 - (optional) an array of attributes to be written to the ```<img>``` element

Returns an HTML ```<img>``` element.

**Note:** Avoid calling this method without passing a $thumbnail_size when instantiating Multiblog\_Query or with a different size than that passed when instantiating. Either case will result in an expensive call to switch_to_blog().

#### get\_the\_post\_thumbnail\_id() ####

Retuns the ID of the post's thumbnail, if one is set. Otherwise returns 0 or Boolean false.

**Note:** Avoid calling when Multiblog\_Query is instantiated with $thumbnail_size = false, as this will result in an expensive call to switch_to_blog().

### Post Metadata ###

If post metadata will be used, it is best to retrieve during instantiation by passing the desired post metadata keys to the constructor. If not obtained during instantiation, metadata can still be retrieved, but it is much more expensive.

#### get\_post\_meta() ####

This function mirrors WordPress's native get\_post\_meta() function, with one exception: instead of passing the a Post ID as the first parameter, a post object is passed instead.

### Post IDs ###

Many developers us WordPress's the\_ID() function to append a unique suffix to HTML element IDs. Since each blog in a multiblog installation has its own Posts table, pulling posts from multiple blogs introduces the chance of ID collisions.

To resolve this, Multiblog\_Query's the\_ID() method appends the blog ID to posts retrieved from alternate blogs.


## Static Members ##

### restore_current_blog() ###

WordPress Core's restore\_current\_blog() function restores the _previous_ blog, not the _current_ blog.
The difference being that when done switching between multiple blogs, the Core function will only step
you back to the most currently switched from blog.

This method jumps all the way to the original blog.