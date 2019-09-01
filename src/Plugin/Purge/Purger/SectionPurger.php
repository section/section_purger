<?php

namespace Drupal\section_purger\Plugin\Purge\Purger;

use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\section_purger\Plugin\Purge\Purger\SectionPurgerBase;

/**
 * Section Purger.
 *
 * @PurgePurger(
 *   id = "section",
 *   label = @Translation("Section Purger"),
 *   configform = "\Drupal\section_purger\Form\SectionPurgerForm",
 *   cooldown_time = 0.2,
 *   description = @Translation("Purger that sends invalidation expressions from your Drupal instance to the Section platform."),
 *   multi_instance = TRUE,
 *   types = {"url", "wildcardurl", "tag", "everything", "wildcardpath", "regex", "path", "domain", "raw"},
 * )
 */
class SectionPurger extends SectionPurgerBase implements PurgerInterface {

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    /* Since we implemented ::routeTypeToMethod(), this exception should not
       ever occur because every invalidation type is routed to a respective function.
       And when it does inevitably get called, it will throw an exception easily visible within the drupal logs.
     */
    throw new \Exception("invalidate() called on a multi-type purger which routes each invalidatiaton type to its own method. This error should never be seen.");
  }

  /**
   * sendReq($invalidation,$uri,$opt)
   * This does all the HTTP dirty work to avoid code repetition.
   * @param Invalidation $invalidation
   * @param string $uri
   * the URL of the API endpoint
   * @param array $opt
   * request options (ie headers)
   * @param string $exp
   * the ban expression
   * @return void
   */
  public function sendReq($invalidation, $uri, $opt,$exp){
    $exp = urlencode($exp); //the banExpression is sent as a parameter in the URL, so things like ampersands, asterisks, question marks, etc will break the parse
    $uri .=$exp; //append the banExpression to the URL
    try {
      $response = $this->client->request($this->settings->request_method, $uri, $opt);
      $invalidation->setState(InvalidationInterface::SUCCEEDED);
    }
    catch(\GuzzleHttp\Exception\ConnectException $e) {
      $invalidation->setState(InvalidationInterface::FAILED);
      $this->logger->critical("http request for ". $uri ." responded with ". $e->getMessage()); //Usually timeouts or other connection issues.
    }
    catch (\Exception $e) {
      $invalidation->setState(InvalidationInterface::FAILED);
      // Log as much useful information as we can.
      $headers = $opt['headers'];
      unset($opt['headers']);
      $debug = json_encode(
        str_replace("\n", ' ',
          [
            'msg' => $e->getMessage(),
            'uri' => $uri,
            'method' => $this->settings->request_method,
            'guzzle_opt' => $opt,
            'headers' => $headers,
            'response' => $response->getStatusCode(),
          ]
        )
      );
      $this->logger->critical($debug);
    }
    
  }

    /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      $this->logger->debug($invalidation->getExpression());
      $exp = 'obj.http.Purge-Cache-Tags ~ "' . $opt["headers"]["purge-cache-tags"] . '"';
      //adds this at the end if this instance has a site name in the configuration, for multi-site pages.
      //the ampersands are url encoded to be %26%26 in sendReq
      if ($this->getSiteName()) {
        $exp .= ' && req.http.host == "' . $this->getSiteName() . '"';
      }
      $this->logger->debug("[TAG] invalidating tag with expression `". $exp ."`");
      $this->sendReq($invalidation,$uri,$opt,$exp);
    }
  }

  /**
   * invalidateUrls(array $invalidations)
   * This will invalidate urls. The protocol is required and 
   * this must contain the hostname, the protocol, and path (if any)
   * The protocol is specific; for example if invalidating an http request, the https equivalent will not be invalidated.
   * e.x.: https://example.com/favicon.ico
   * @param array $invalidations
   * This takes in an array of Invalidation, processing them all in a loop, generally from the purge queue.
   * @return void
   */
  public function invalidateUrls(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      $invalidation->validateExpression();
      $parse = parse_url($invalidation->getExpression());
      if(!$parse){
        $invalidation->setState(InvalidationInterface::FAILED);
        throw new InvalidExpressionException('URL Invalidation failed with '. $invalidation->getExpression());
      }
      //Sanitize the path
      $patterns = array(
            '/([[\]{}()+?".,\\^$|#])/' // Escape regex characters except *
          , '/\*/'                  // Replace * with .* (for actual Varnish regex)
        );
      $replace = array(
            '\\\$1' // Escape regex characters except *
          , '.*'    // Replace * with .* (for actual Varnish regex)
        );
      $exp = 'req.http.X-Forwarded-Proto == "'. $parse['scheme'] . '" && ' . '" && req.http.host == "' . $parse['host'] . '" && req.url ~ "^';
      $exp .= preg_replace($patterns, $replace, substr($parse['path'], 1) . $parse['query'] . $parse['fragment']);
      //adds this at the end if this instance has a site name in the configuration, for multi-site pages.
      //the ampersands are url encoded to be %26%26 in sendReq
        $exp .= '$"';
      $this->logger->debug("[URL] expression `". $invalidation->getExpression() ."` was replaced to be: `". $exp . "`");
      $this->sendReq($invalidation,$uri,$opt,$exp);
    }
  }

  /**
   * invalidateEverything($invalidations)
   * This will use obj.status != 0 to ban every page that does not have an empty response
   * @param array $invalidations
   * This takes in an array of Invalidation, processing them all in a loop, generally from the purge queue.
   * @return void
   */
  public function invalidateEverything(array $invalidations){
    //invalidates everything within the siteName;
    $globalExpression = "obj.status != 0";
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      $exp = "obj.status != 0";
      //adds this at the end if this instance has a site name in the configuration, for multi-site pages.
      //the ampersands are url encoded to be %26%26 in sendReq
      if ($this->getSiteName()) {
        $exp .= ' && req.http.host == "' . $this->getSiteName() . '"';
      }
      $this->logger->debug("[EVERYTHING] invalidating with expression `". $exp ."`");
      $this->sendReq($invalidation,$uri,$opt,$exp);
    }
  }

  /**
   * invalidatePaths(array $invalidations)
   * This will invalidate paths. As per the purger module guidelines,
   * this should not start with a slash, and should not contain the hostname.
   * e.x.: favicon.ico
   * @param array $invalidations
   * This takes in an array of Invalidation, processing them all in a loop, generally from the purge queue.
   * @return void
   */
  public function invalidatePaths(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      
      //sanitize the path, stripping of regex and escaping quotes
      $patterns = array(
        '/^\//',
        '/([[\]{}()+?.,\\^$|#])/' // Escape regex characters except *
          , '/\*/'                // Replace * with .* (for actual Varnish regex)
        );
      $replace = array(
          ''
          , '\\\$1'                 // Escape regex characters except *
          , '.*'                  // Replace * with .* (for actual Varnish regex)
        );
      $exp = 'req.url ~ "^/';        // base varnish ban expression for paths
      $exp .= preg_replace($patterns, $replace, $invalidation->getExpression()) . '$"';
      
      //adds this at the end if this instance has a site name in the configuration, for multi-site pages.
      //the ampersands are url encoded to be %26%26 in sendReq
      if ($this->getSiteName()) {
        $exp .= ' && req.http.host == "' . $this->getSiteName() . '"';
      }
      $this->logger->debug("[PATH] expression `". $invalidation->getExpression() ."` was replaced to be: `". $exp . "`");
      $this->sendReq($invalidation,$uri,$opt,$exp);
    }
  }

  /**
   * invalidateDomain(array $invalidations)
   * This will invalidate a hostname.
   * This should not contain the protocol, simply the hostname
   * e.x.: example.com
   * @param array $invalidations
   * This takes in an array of Invalidation, processing them all in a loop, generally from the purge queue.
   * @return void
   */
  public function invalidateDomain(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      // from VarnishManageController.ts in aperture
      $exp = 'req.http.host == "' .  $invalidation->getExpression().'"';
      $this->logger->debug("[DOMAIN] expression `". $invalidation->getExpression() ."` was replaced to be: `". $exp . "`");
      $this->sendReq($invalidation,$uri,$opt,$exp);
    }
  }


/* Since by default invalidateURLs() has the ability to handle wildcard urls, this is just an alias.
   This method is still necessary to exist because purge itself has certain validations for each type. */
  public function invalidateWildcardUrls(array $invalidations){
  $this->invalidateUrls($invalidations);
}

/* Since by default invalidatePaths() has the ability to handle wildcard urls, this is just an alias.
   This method is still necessary to exist because purge itself has certain validations for each type. */
  public function invalidateWildcardPaths(array $invalidations){

  $this->invalidatePaths($invalidations);
}
  

  /**
   * invalidateRegex(array $invalidations)
   * This allows for raw varnish ban expressions.
   * !! This does not simply allow a regex expression for path !! 
   * I  broke the standards of the purge module because I was spending too much time trying to figure out how to
   * implement my own invalidation type. I figured if regex is allowed why not go the whole mile and allow custom ban expressions.
   * e.x.: obj.status == "404" && req.url ~ node\/(?).* - would clear the cache of 404'd nodes.
   * @param array $invalidations
   * This takes in an array of Invalidation, processing them all in a loop, generally from the purge queue.
   * @return void
   */
  public function invalidateRegex(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      
      $this->logger->debug("[REGEX] expression `". $invalidation->getExpression() ."` is a raw ban expression and requires `req.url ~ ` preceeding the regex for standard use. `%26%26 req.http.host == [site name from config]` will NOT be appended at the end regardless of whether or not the multisite name is specified.");
      /* this line is not added because of the above line.
         $uri .= 'req.url ~ ' . $exp; 
      */
      $this->sendReq($invalidation,$uri,$opt,$invalidation->getExpression());
    }

    /* If I could actually manage to implement a new invalidation type for raw expressions, invalidateRegex would look like this */
    /**
       * invalidateRegex(array $invalidations)
       * This allows for a regular expression match of a path.
       * e.x.: obj.status == "404" && req.url ~ "node\/(?).*" - would clear the cache of 404'd nodes.
       * @param array $invalidations
       * This takes in an array of Invalidation, processing them all in a loop, generally from the purge queue.
       * @return void
      */
  /*
    public function invalidateRegex(array $invalidations){
      foreach ($invalidations as $invalidation) {
        $invalidation->setState(InvalidationInterface::PROCESSING);
        $token_data = ['invalidation' => $invalidation];
        $uri = $this->getUri($token_data);
        $opt = $this->getOptions($token_data);
        $exp = 'req.url ~ ' . $invalidation->getExpression();
        //adds this at the end if this instance has a site name in the configuration, for multi-site pages.
        //the ampersands are url encoded to be %26%26 in sendReq
        if ($this->getSiteName()) {
          $exp .= "&& req.http.host == " . $this->getSiteName();
        }
        $this->logger->debug("expression `". $invalidation->getExpression() ."` was replaced to be: `req.url ~ ". $exp . " `");
        $this->sendReq($invalidation,$uri,$opt);
      }
    }
    */
  }

  /**
   * public function routeTypeToMethod($type)
   *
   * @param string $type
   *   The type of invalidation(s) about to be offered to the purger.
   *
   * @return string
   *   The PHP method name called on the purger with a $invalidations parameter.
   */
  
  public function routeTypeToMethod($type) {
    /*
    Purge has to be crystal clear about what needs invalidation towards its purgers,
    and therefore has the concept of invalidation types. Individual purgers declare
    which types they support and can even declare their own types when that makes sense.
    Since Drupal invalidates its own caches using cache tags, the tag type is the most
    important one to support in your architecture. (and is supported, and required)

    domain        Invalidates an entire domain name.
    everything    Invalidates everything.
    path          Invalidates by path, e.g. news/article-1. This should not start with a slash, and should not contain the hostname.
    regex         This doesn't actually invalidate by regular expression. it allows for varnish ban expressions. e.g. obj.status == 404 && req.url ~ node\/(?).* !!!!!!!!!!!!!!! Invalidates by regular expression, e.g.: \.(jpg|jpeg|css|js)$.
    tag           Invalidates by Drupal cache tag, e.g.: menu:footer.
    url           Invalidates by URL, e.g. http://site.com/node/1. The protocol is specific; for example if invalidating an http request, the https equivalent will not be invalidated
    wildcardpath  Invalidates by path, e.g. news/*.
    wildcardurl   Invalidates by URL, e.g. http://site.com/node/*.
*/
    $methods = [
      'tag'          => 'invalidateTags',
      'domain'       => 'invalidateDomain',
      'url'          => 'invalidateUrls',
      'wildcardurl'  => 'invalidateWildcardUrls',
      'everything'   => 'invalidateEverything',
      'wildcardpath' => 'invalidateWildcardPaths',
      'path'         => 'invalidatePaths',
      'regex'        => 'invalidateRegex',
      'raw'          => 'invalidateRawExpression'
    ];
    return isset($methods[$type]) ? $methods[$type] : 'invalidate';
  }
}
