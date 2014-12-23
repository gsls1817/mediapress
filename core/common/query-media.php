<?php

/**
 * MediaPress Media Query
 */
/**
 * The Gallery Media Query class.
 *
 * @since 1.1
 */
class MPP_Media_Query extends WP_Query {

    private $post_type;
    
    public function __construct( $query = '' ) {
        $this->post_type = mpp_get_media_post_type(); 
        
        parent::__construct(  $query );
        
	}
    
    public function query( $args ){
        
        //make sure that the query params was not built before
        if( !isset( $args['_mpp_mapped_query'] ) )
            $args = self::build_params( $args );
        
        parent::query( $args );
        
    }
    //map gallery parameters to wp_query parameters
    public function build_params( $args ){
        
        $defaults = array(
                'type'              => array_keys( mpp_get_active_types() ),//false, //media type, all,audio,video,photo etc
                'id'                => false, //pass specific media id
                'in'                => false, //pass specific media ids as array
                'exclude'           => false, //pass media ids to exclude
                'slug'              => false,//pass media slug to include
                'status'            => array_keys( mpp_get_active_statuses() ),//false, //public,private,friends one or more privacy level
                'component'         => array_keys( mpp_get_active_components() ),//false, //one or more component name user,groups, evenets etc
                'component_id'      => false,// the associated component id, could be group id, user id, event id
                //gallery specific
                'gallery_id'        => false,
                'galleries'         => false,
                'galleries_exclude' => false,
            
                //
                'per_page'          => mpp_get_option( 'media_per_page' ), //how many items per page
                'offset'            => false, //how many galleries to offset/displace
                'page'              => false,//which page when paged
                'nopaging'          => false, //to avoid paging
                'order'             => 'DESC',//order 
                'orderby'           => false,//none, id, user, title, slug, date,modified, random, comment_count, meta_value,meta_value_num, ids
                //user params
                'user_id'           => false,
                'user_name'         => false,
                'scope'             => false,
                'search_terms'      => '',
                
            //time parameter
                'year'              => false,//this years
                'month'             => false,//1-12 month number
                'week'              => '', //1-53 week
                'day'               => '',//specific day
                'hour'              => '',//specific hour
                'minute'            => '', //specific minute
                'second'            => '',//specific second 0-60
                'yearmonth'         => '',// yearMonth, 201307//july 2013
                //'meta_key'          => false,
                //'meta_value'        => false,
                'meta_query'        => false,
                'fields'            => false,//which fields to return ids, id=>parent, all fields(default),
                
        );
        
     
        //build params for WP_Query
        /**
		 * If wer are querying for a single gallery
		 * and the gallery media were sorted by the user, show the media s in the sort order insted of the default date 
		 */
        if( isset( $args['gallery_id'] ) && mpp_is_gallery_sorted( $args['gallery_id'] ) )
				$defaults['orderby']	= 'menu_order';
		
    $r = wp_parse_args( $args, $defaults );
    extract( $r, EXTR_SKIP );
    
   
    
    //build the wp_query args
    $wp_query_args = array(
            'post_type'             => mpp_get_media_post_type(),
            'post_status'           => 'any',
            'p'                     => $id,
            'post__in'              => $in,
            'post__not_in'          => $exclude,
            'name'                  => $slug,

            //gallery specific
            'post_parent'           => $gallery_id,
            'post_parent__in'       => !empty( $galleries)? (array) $galleries : 0,
            'post_parent__not_in'   => !empty( $galleries_exclude ) ? (array) $galleries_exclude :0,
            'posts_per_page'        => $per_page,
            'paged'                 => $page,
            'offset'                => $offset,
            'nopaging'              => $nopaging,
            //user params
            'author'                => $user_id,
            'author_name'           => $user_name,
            //date time params

            'year'                  => $year,
            'monthnum'              => $month,
            'w'                     => $week,
            'day'                   => $day,
            'hour'                  => $hour,
            'minute'                => $minute,
            'second'                => $second,
            'm'                     => $yearmonth,
            //order by
            'order'                 => $order,
            'orderby'               => $orderby,
            's'                     => $search_terms,
            //meta key, may be we can set them here?
            //'meta_key'              => $meta_key,
            //'meta_value'            => $meta_value,
            //which fields to fetch
            'fields'                => $fields,
            '_mpp_mapped_query'      => true,
    );
    
    //TODO: SCOPE
    //
    
    
    //we will need to build tax query/meta query
    
    $tax_query = array();
    $gmeta_query = array();
   
    if( isset( $meta_key ) && $meta_key )
        $wp_query_args['meta_key'] = $meta_key;
    
    if( isset($meta_key) && $meta_key && isset( $meta_value )  )
        $wp_query_args['meta_value'] = $meta_value;
    
    if( !empty( $meta_query ))
        $gmeta_query = $meta_query;
    
    
    //we will need to build tax query/meta query
    
    //type, audio video etc
    //if type is given and it is valid gallery type
    //Pass one or more types
    if( !empty( $type ) && mpp_are_registered_gallery_types( $type ) ){
        
        $type = mpp_string_to_array( $type ); 
        $type = array_map( 'mpp_underscore_it', $type );
        
        $tax_query[] = array(
                'taxonomy'  => mpp_get_type_taxname(),
                'field'     => 'slug',
                'terms'     => $type,
                'operator'  => 'IN',
        );
    }
    
    
    
    
    //privacy
    //pass ne or more privacy level
    if( !empty( $status ) && mpp_are_registered_gallery_statuses( $status ) ){
        
        $status = mpp_string_to_array( $status );
        $status = array_map( 'mpp_underscore_it', $status );
        
        $tax_query[] = array(
                'taxonomy' => mpp_get_status_taxname(),
                'field'=>'slug',
                'terms'=> $status,
                'operator' =>'IN'
        ); 
    }
    
    if( !empty ( $component ) && mpp_are_registered_gallery_components( $component ) ){
        
        $component = mpp_string_to_array( $component ); 
        $component = array_map( 'mpp_underscore_it', $component );
        
        $tax_query[] = array(
                'taxonomy' => mpp_get_component_taxname(),
                'field'=> 'slug',
                'terms'=> $component,
                'operator' =>'IN'
        ); 
       
    }
    
    

    //done with the tax query
    
    if( count( $tax_query ) > 1 ){
        
        $tax_query['relation'] = 'AND';
        
    }
   if( !empty( $tax_query ) )
    $wp_query_args['tax_query'] = $tax_query;
    
   
//now, for component
    if( !empty( $component_id ) ){
     
        $meta_compare = '=';
    
        if( is_array( $component_id ) )
            $meta_compare = 'IN';
    
        $gmeta_query[] = array(
            'key'=>     '_mpp_component_id',
            'value'=>   $component_id,
            'compare'=> $meta_compare,
            'type' =>   'UNSIGNED'

        );
    
    }
    //also make sure that it only looks for gallery media
    
    $gmeta_query[] = array(
        
        'key'   => '_mpp_is_mpp_media',
        'value' => 1,
        'compare' => '=',
        'type'  => 'UNSIGNED'
    );
    
    //should we avoid the orphaned media
	//Let us discuss with the community and get it here
	if( ! mpp_get_option( 'show_orphaned_media' ) ) {
		
		$gmeta_query[] = array(

			'key'		=> '_mpp_is_orphan',
			'compare'	=> 'NOT EXISTS',
			
		);
	
	}
     //reset meta query
    if(!empty($gmeta_query)){
        
        $wp_query_args['meta_query'] = $gmeta_query;
    }
    
    //$wp_querprint_nice(y_args);
   
   //print_nice($wp_query_args);
    return $wp_query_args;
   
   
    //http://wordpress.stackexchange.com/questions/53783/cant-sort-get-posts-by-post-mime-type
    }
      
    public function get_media(){
        
        return parent::get_posts();
    }
    

    public function next_media(){
        
        return parent::next_post();
        
    }
    //undo the pointer to next
    public function reset_next(){
        
       
		$this->current_post--;

		$this->post = $this->posts[$this->current_post];
		return $this->post;
        
    }

    
    public function the_media() {
                
        global $post;
		$this->in_the_loop = true;

           
         if ( $this->current_post == -1 ) // loop has just started
                do_action_ref_array( 'mediapress_media_loop_start', array(&$this));

		$post = $this->next_media();
		
        
        setup_postdata( $post );
         
        mediapress()->current_media = mpp_get_media( $post );
        //mpp_setup_media_data( $post );
       
		
       
    }
    
    public function have_media(){
        
        return parent::have_posts();
    }
    
    public function rewind_media() {
        parent::rewind_posts();
    }
    
    
    public function is_main_query(){

        $mediappress = mediapress();
        
        return $this == $mediappress->the_media_query;

    }
    
    
	function reset_media_data() {
        parent::reset_postdata();
		if ( ! empty( $this->post ) ) {
			mediapress()->current_media = mpp_get_media( $this->post );
			
		}
	}
    
    /**
     * Putting helpers to allow easy pagination in the loops
     */
    public function paginate(){
        
        $total = $this->max_num_pages;
                // only bother with the rest if we have more than 1 page!
        if ( $total > 1 )  {
            // get the current page
            if ( !$current_page = $this->get('paged') )
                    $current_page = 1;
            // structure of “format” depends on whether we’re using pretty permalinks
            $perma_struct=get_option('permalink_structure');
            $format = empty( $perma_struct ) ? '&page=%#%' : 'page/%#%/';
     
     
    
            $link=  get_pagenum_link(1) ;
     
    
            //$format=$format;
            $base = $link;
           return paginate_links(array(
                 'base' => $base.'%_%',
                 'format' => $format,
                 'current' => $current_page,
                 'total' => $total,
                 'mid_size' => 4,
                 'type' => 'list'
            ));
        }
    }
      
    
    function pagination_count(){
        
        
            
        
        $paged = $this->get( 'paged' )? $this->get( 'paged' ) : 1;
        $posts_pet_page = $this->get( 'posts_per_page' );

        $from_num = intval( ( $paged - 1 ) * $posts_pet_page ) + 1;

        $to_num = ( $from_num + ( $posts_pet_page - 1 ) > $this->found_posts ) ? $this->found_posts : $from_num + ( $posts_pet_page - 1) ;

        echo sprintf( __( 'Viewing  %d to %d (of %d %s)', 'mediapress' ), $from_num, $to_num, $this->found_posts, mpp_get_media_type() ); 
    }
	
	/**
	 * Get all the ids in this request
	 */
	public function get_ids(){
		$ids = array();
		
		if( empty( $this->request ) )
			return $ids;
		global $wpdb;
		$ids = $wpdb->get_col( $this->request);
		return $ids;
	}
}

/**
 * Reset global media data
 */
function mpp_reset_media_data(){
    
    mediapress()->the_media_query->reset_media_data();
    wp_reset_postdata();
}