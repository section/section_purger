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
    // Since we implemented ::routeTypeToMethod(), this exception
    // shouldn't ever occur because every invalidation type is routed to a respective function.
    throw new \Exception("invalidate() called on a multi-type purger which routes each invalidatiaton type to its own method. Invalid invalidation type?");
  }

  public function sendReq($invalidation, $uri, $opt){

    /*
      get invalidation expression
      json encode expression
        {
          "ban": "req.url ~ /"
        }
        
    */
    try {
      $this->client->request($this->settings->request_method, $uri, $opt);
      $invalidation->setState(InvalidationInterface::SUCCEEDED);
    }
    catch(\GuzzleHttp\Exception\ConnectException $e) {
      $invalidation->setState(InvalidationInterface::SUCCEEDED);
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
          ]
        )
      );
    /*  $this->logger()->emergency("item failed due @e, details (JSON): @debug",
        ['@e' => get_class($e), '@debug' => $debug]
      );
      */
    }
  }
  public function invalidateTags(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      $this->logger->debug($invalidation->getExpression());
      $exp = 'obj.http.Purge-Cache-Tags ~ ' . $opt["headers"]["purge-cache-tags"];
      //adds this at the end if this instance has a site name in the configuration, for multi-site pages.
      //%26 is for encoding the ampersand: &
      if ($this->getSiteName()) {
        $exp .= " %26%26 req.http.host == " . $this->getSiteName();
      }
      $uri .= urlencode($exp);
      $this->logger->debug("invalidating tag with expression `". $exp ."`");
      $this->sendReq($invalidation,$uri,$opt);
    }
  }
  public function invalidateUrls(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      
      // from VarnishManageController.ts in aperture
      //convert the url into a path
      $patterns = array(
        "/(https?:\/\/.*?)?\//"     // Remove http(s)://www.domain.com from the url
        , '/([[\]{}()+?.,\\^$|#])/' // Escape regex characters except *
          , '/\*/'                  // Replace * with .* (for actual Varnish regex)
        );
      $replace = array(
          '/'       // Remove http(s)://www.domain.com from the url
          , '\\\$1' // Escape regex characters except *
          , '.*'    // Replace * with .* (for actual Varnish regex)
        );
      $exp = 'req.url ~ ';
      $exp .= preg_replace($patterns, $replace, $invalidation->getExpression());
      //adds this at the end if this instance has a site name in the configuration, for multi-site pages.
      //%26 is for encoding the ampersand: &
      if ($this->getSiteName()) {
        $exp .= " %26%26 req.http.host == " . $this->getSiteName();
      }
      $this->logger->debug("expression `". $invalidation->getExpression() ."` was replaced to be: `". $exp . "`");
      
      $uri .= urlencode($exp);
      $this->sendReq($invalidation,$uri,$opt);
    }
  }

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
      //%26 is for encoding the ampersand: &
      if ($this->getSiteName()) {
        $exp .= " %26%26 req.http.host == " . $this->getSiteName();
      }
      
      $this->logger->debug("invalidating everything with expression `". $exp ."`");
      
      $uri .= urlencode($exp);
      $this->sendReq($invalidation,$uri,$opt);
    }
  }
  public function invalidatePaths(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);

      // from VarnishManageController.ts in aperture
      //strip the path of regex, and escape
      $patterns = array(
        '/([[\]{}()+?.,\\^$|#])/' // Escape regex characters except *
          , '/\*/'                  // Replace * with .* (for actual Varnish regex)
        );
      $replace = array(
          '\\\$1' // Escape regex characters except *
          , '.*'    // Replace * with .* (for actual Varnish regex)
        );
      $exp = 'req.url ~ ';
      $exp .= preg_replace($patterns, $replace, $invalidation->getExpression());
      
      //adds this at the end if this instance has a site name in the configuration, for multi-site pages.
      //%26 is for encoding the ampersand: &
      if ($this->getSiteName()) {
        $exp .= " %26%26 req.http.host == " . $this->getSiteName();
      }

      $this->logger->debug("expression `". $invalidation->getExpression() ."` was replaced to be: `". $exp . "`");
      
      $uri .= urlencode($exp);
      $this->sendReq($invalidation,$uri,$opt);
    }
  }
  public function invalidateDomain(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);

      // from VarnishManageController.ts in aperture
      //strip the path of regex, and escape
      $exp = 'req.http.host == ' .  $invalidation->getExpression();
      

      $this->logger->debug("expression `". $invalidation->getExpression() ."` was replaced to be: `". $exp . "`");
      
      $uri .= urlencode($exp);
      $this->sendReq($invalidation,$uri,$opt);
    }
  }


/* Since by default invalidateURLs() has the ability to handle wildcard urls, this is just an alias.
   This method is still necessary to exist because purge itself has certain validations for each type. */
  public function invalidateWildcardUrls(array $invalidations){
  $this->invalidatePaths($invalidations);
}

/* Since by default invalidatePaths() has the ability to handle wildcard urls, this is just an alias.
   This method is still necessary to exist because purge itself has certain validations for each type. */
  public function invalidateWildcardPaths(array $invalidations){

  $this->invalidatePaths($invalidations);
}
  
/* If I could actually manage to implement a raw invalidation type, invalidateRegex would look like this */
/*
  public function invalidateRegex(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      $exp = 'req.url ~ ' . $invalidation->getExpression();
      //adds this at the end if this instance has a site name in the configuration, for multi-site pages.
      //%26 is for encoding the ampersand: &
      if ($this->getSiteName()) {
        $exp .= " %26%26 req.http.host == " . $this->getSiteName();
      }
      $this->logger->debug("expression `". $invalidation->getExpression() ."` was replaced to be: `req.url ~ ". $exp . " `");
      $uri .= urlencode($exp);
      
      $this->sendReq($invalidation,$uri,$opt);
    }
  }
  */
  public function invalidateRegex(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      
      $this->logger->debug("expression `". $invalidation->getExpression() ."` is a raw ban expression and requires `req.url ~ ` preceeding the regex for standard use. `%26%26 req.http.host == [site name from config]` will NOT be appended at the end regardless of whether or not the multisite name is specified.");
      /* this line is not added because of the above line.
         $uri .= 'req.url ~ ' . $exp; 
      */
      $uri .= urlencode($invalidation->getExpression());
      $this->sendReq($invalidation,$uri,$opt);
    }
  }
  public function routeTypeToMethod($type) {
    /*
    Purge has to be crystal clear about what needs invalidation towards its purgers,
    and therefore has the concept of invalidation types. Individual purgers declare
    which types they support and can even declare their own types when that makes sense.
    Since Drupal invalidates its own caches using cache tags, the tag type is the most
    important one to support in your architecture. (and is supported, and required)

    domain        Invalidates an entire domain name.
    everything    Invalidates everything.
    path          Invalidates by path, e.g. news/article-1.
    regex         Invalidates by regular expression, e.g.: \.(jpg|jpeg|css|js)$.
    tag           Invalidates by Drupal cache tag, e.g.: menu:footer.
    url           Invalidates by URL, e.g. http://site.com/node/1.
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
