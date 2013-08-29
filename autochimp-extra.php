<?php

/**
 * Autochimp extra content fields
 * 
 * Register handlers.  When a post contains a field/section with a name that matches that regex,
 * the handler is asked to provide some content.
 * 
 * The first handler with a matching regex wins
 */

class ACP
{
    private static $instance = false;
    public function getInstance()
    {
        if(self::$instance === false)
            self::$instance = new ACP();
            
        return self::$instance;
    }
    
    private $plugins = array();
    public function registerPlugin($field_regex, ACP_Plugin $handler)
    {
        $this->plugins[$field_regex] = $handler;
    }

    public function getExtraContent(MCAPI_13 $api, $templateID)
    {
        $content = array();
        $fields = $api->templateInfo($templateID);
        
        var_dump($fields);
        
        // For each field in the template, see if there is a registered handler and run it if there is
        foreach($fields['sections'] as $fname)
        {
            // Try each regex
            foreach($this->plugins as $regex=>$plugin)
            {
                if(preg_match($regex, $fname))
                {
                    $content['html_'.$fname] = $plugin->getContent($templateID, $fname);
                    break;
                }
            }
        }
        
        return $content;
    }
}

interface ACP_Plugin
{
    public function getContent($templateID, $fieldName);
}

/**
 * Generate fields for recent posts
 */
class ACP_RecentPosts implements ACP_Plugin
{
        public function getContent($templateID, $fieldName)
	{
		list($x, $categoryID, $offset) = explode('_', $fieldName);
                
                return getPost($categoryID, $offset);
	}
        
        /**
         * 
         * @param type $categoryID
         * @param type $excerptLength
         * @param type $txtMore
         * @param type $offset : Offset the post count by this much (to eg skip the first post in a category)
         */
        protected function getPost($categoryID, $offset)
        {
            // look up the right post counter
            $postnum = $this->getCatCounter($catId, $offset);
            
            // Load the post
            $q = new WP_Query('cat='.$catId);
            
            $post = $q->posts[$postnum];
            
            // Generate the excerpt (borrowed from AutoChimp proper)
            if ( 0 == strlen( $post->post_excerpt ) )
            {
                    // Handmade function which mimics wp_trim_excerpt() (that function won't operate
                    // on a non-empty string)
                    $postContent = AC_TrimExcerpt( $post->post_content );
            }
            else
            {
                    $postContent = apply_filters( 'the_excerpt', $post->post_excerpt );
                    // Add on a "Read the post" link here
                    $permalink = get_permalink( $postID );
                    $postContent .= "<p>Read the post <a href=\"$permalink\">here</a>.</p>";
                    // See http://codex.wordpress.org/Function_Reference/the_content, which
                    // suggests adding this code:
                    $postContent = str_replace( ']]>', ']]&gt;', $postContent );
            }
            
            // Return some HTML
            return $postContent;
            
        }

        /**
         * We need to remember which posts have already been returned in each category!
         * 
         * By default, the counter is incremented by one.  By specifying an $offset > 0, that
         * can be increased.  (eg the first tag in the message might need to skip the post
         * that is the basis for the actual message!)
         */
        private $catcounters = array();
        protected function getCatCounter($catId, $offset=0)
        {
            if(!array_key_exists($catId, $this->catcounters))
            {
                $this->catCounters[$catId] = -1;
            }
            
            $this->catCounters[$catId] = $this->catCounters[$catId] + 1 + $offset;
            
            return $this->catCounters[$catId];
        }
}

ACP::getInstance()->registerPlugin('/recent_[0-9]{1,4}_[0-9]{1,3}/i', new ACP_RecentPosts());

?>
