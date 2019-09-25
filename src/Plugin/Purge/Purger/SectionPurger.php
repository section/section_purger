<?php

namespace Drupal\section_purger\Plugin\Purge\Purger;

use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\section_purger\Plugin\Purge\Purger\SectionPurgerBase;
use Drupal\section_purger\Plugin\Purge\TagsHeader\CacheTagsHeaderValue;
use Drupal\section_purger\Entity\Hash;
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
   * {@inheritdoc}
   */
  public function invalidateTags(array $invalidations){
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      $this->logger->debug($invalidation->getExpression());
      $tag = new CacheTagsHeaderValue([$invalidation->getExpression()], Hash::cacheTags([$invalidation->getExpression()]) );
      $exp = 'obj.http.Section-Cache-Tags ~ "' . $tag . '"';
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
