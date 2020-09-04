<?php 

# Example: pcraw.php teacherfunder.com

class Pcraw{

    public $site_url;
    public $is_basic_auth = false;
    public $username = '';
    public $password = '';
    public $limit = PHP_INT_MAX;
    public $urls_found = [];
    public $urls_parsed = [];

    /**
     * Constructor
     */
    function __construct(){
        
        global $argv;

        # Check if url is passed
        if( isset( $argv[1] ) ){
            $this->site_url = trim($argv[1]);
            $this->site_url_nopro = preg_replace("/https?:\/\//", "", $this->site_url);
            $this->site_url_nopro = preg_replace("/\/$/", "", $this->site_url_nopro);
        }

        # Check if valid url
        if( ! filter_var($this->site_url, FILTER_VALIDATE_URL) ) {
            print "\n[Errro] Invalid url.\n";
            die;
        }

        # Get passed settings
        if( isset( $argv[2] ) && ( $argv[2] == 1 || strtolower($argv[2]) == 'true' ) ){
            $this->is_basic_auth = true;
        }

        if( isset( $argv[3] ) ){
            $this->username = $argv[3];
        }

        if( isset( $argv[4] ) ){
            $this->password = $argv[4];
        }    

        if( isset( $argv[5] ) ){
            $this->limit = $argv[5];
        }    

        # Show time
        $result = $this->get_page_content($this->site_url);
        
        if( ! $result ){
            print "\n[Error] Site does not work or Unauthorized.\n";
            die;
        }

        $this->urls_found[] = $this->site_url;
        $this->urls_parsed[] = $this->site_url;
        
        $this->parse_content($result);
        $this->loop_now();

        print "\n[Succes] Done parsing.\n";
        var_dump(count($this->urls_found));

        $this->save_result();
    }

    /**
     * Save result to a file
     *
     * @return void
     */
    function save_result(){

        $content = "";

        foreach( $this->urls_parsed as $url ){
            $content .= $url . PHP_EOL;
        }

        @file_put_contents( __DIR__ . "/" . uniqid() . "_urls_found.txt", $content );
    }

    /**
     * Log
     *
     * @param string $url
     * @return void
     */
    function log( $url ){
        print "\nParsing: {$url}";
    }

    /**
     * Get page html content
     *
     * @param string $url
     * @return string
     */
    function get_page_content( $url ){
        
        $this->log($url);

        $context = null;
    
        if( $this->is_basic_auth ){
    
            $auth = base64_encode("{$this->username}:{$this->password}");
        
            $context = stream_context_create([
                "http" => [
                    "header" => "Authorization: Basic $auth"
                ]
            ]);
        }

        return @file_get_contents( $url, false, $context );
    }

    /**
     * Parse html content and find links
     *
     * @param string $content
     * @return array
     */
    function parse_content( $content ){
        
        $urls = [];

        preg_match_all("/href=[\"\'](.+?)[\"\']/", $content, $matches );
        
        if( ! isset( $matches[1] ) ){
            return $this->urls_found;
        }
    
        // only local urls
        foreach( $matches[1] as $url ){
    
            if( isset( $this->urls_found[$url] ) ) continue;
            if( $url === '' ) continue;
            if( $url === '/' ) continue;
            if( $url === '#' ) continue;
            if( preg_match("/^javascript/", $url) ) continue;
    
            $pattern = str_replace('.', '\.', $this->site_url_nopro);
    
            if( preg_match("/^(https?:\/\/".$pattern."|\/)/i", $url) ){
                $urls[] = $url;
            }
        }
    
        // remove assets urls and external urls
        foreach( $urls as $key => $url ){
                
            if( preg_match("/\.(js|css|png|jpg|gif|jpeg|xml|ico)/i", $url ) ){
                unset($urls[$key]);
            }
    
            $pattern = str_replace('.', '\.', $this->site_url_nopro);
    
            // starts with // but external url
            if( preg_match("/^\/\//i", $url) && ! preg_match("/^\/\/".$pattern."/i", $url) ){
                unset($urls[$key]);
            }
        }
    
        // exlcude these paths
        foreach( $urls as $key => $url ){
            if( preg_match("/(\/wp-json\/|xmlrpc\.php|wp\-content|wp\-admin)/i", $url ) ){
                unset($urls[$key]);
            }
        }

        // add domain to /example/
        foreach( $urls as $key => $url ){
            if( preg_match("/^\/[0-9a-z]/i", $url ) ){
                $urls[$key] = $this->site_url . preg_replace("/^\//", "", $url);
            }
        }

        // no duplications
        foreach( $urls as $key => $url ) {
            if( ! in_array( $url, $this->urls_found ) ){
                $this->urls_found[] = $url;
            }
        }

    }

    /**
     * Loop
     *
     * @return void
     */
    function loop_now(){

        $are_all_parsed = true;

        foreach( $this->urls_found as $url ){
            if( in_array( $url, $this->urls_parsed ) ) continue;

            $result = $this->get_page_content( $url );
            $this->urls_parsed[] = $url;

            // something wen wrong
            if( ! $result ){
                continue;
            }

            $this->parse_content($result);
        }

        foreach( $this->urls_found as $url ){
            if( ! in_array( $url, $this->urls_parsed ) ) {
                $are_all_parsed = false;
                break;
            }
        }

        if( ! $are_all_parsed ) $this->loop_now();

        return true;
    }

}

// Init
new Pcraw();
