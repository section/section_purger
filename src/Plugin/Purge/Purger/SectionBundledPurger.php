<?php

namespace Drupal\section_purger\Plugin\Purge\Purger;

use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\section_purger\Plugin\Purge\Purger\SectionPurgerBase;
use Drupal\section_purger\Plugin\Purge\Purger\SectionPurger;
use Drupal\section_purger\Plugin\Purge\TagsHeader\CacheTagsHeaderValue;
use Drupal\section_purger\Entity\Hash;

/**
 * Section Bundled Purger.
 *
 * @PurgePurger(
 *   id = "sectionbundled",
 *   label = @Translation("Section Bundled Purger"),
 *   configform = "\Drupal\section_purger\Form\SectionBundledPurgerForm",
 *   cooldown_time = 0.0,
 *   description = @Translation("Configurable purger that sends a single HTTP request for a set of invalidation instructions."),
 *   multi_instance = TRUE,
 *   types = {"url", "raw", "wildcardurl", "tag", "everything", "wildcardpath", "regex", "path", "domain"},
 * )
 */
class SectionBundledPurger extends SectionPurgerBase implements PurgerInterface
{
    
    /**
    * {@inheritdoc}
    */
    public function invalidate(array $invalidations)
    {
        /* Since we implemented ::routeTypeToMethod(), this exception should not
        ever occur because every invalidation type is routed to a respective function.
        And when it does inevitably get called, it will throw an exception easily visible within the drupal logs.
        */
        throw new \Exception("invalidate() called on a multi-type purger which routes each invalidatiaton type to its own method. This error should never be seen.");
    }
    
    /**
     * group(array $invalidations)
     * This takes an invalidations array and returns groups of 250 invalidations.
     * @param array $invalidations
     * @return $groups
     */
    public function group(array $invalidations)
    {
        $group = 0;
        $groups = [];
        foreach ($invalidations as $invalidation) {
            if (!isset($groups[$group])) {
                $groups[$group] = ['expression' => [], ['objects' => []]];
            }
            if (count($groups[$group]['expression']) >= 250) {
                $group++;
            }
            try {
                $invalidation->validateExpression();
                $parse = parse_url($invalidation->getExpression());
                if (!$parse) {
                    $invalidation->setState(InvalidationInterface::FAILED);
                    throw new InvalidExpressionException('URL Invalidation failed with '. $invalidation->getExpression());
                }
            } catch (\Exception $e) {
                $this->logger->error("Invalid Expression: " .$invalidation->getExpression() . "     -  ". $e->getMessage());
                continue;
            }
            $groups[$group]['objects'][] = $invalidation;
            $groups[$group]['expression'][] = $invalidation->getExpression();
        }
        return $groups;
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateTags(array $invalidations)
    {
        $groups = $this->group($invalidations);
        foreach ($groups as $group) {
            $invalidation = $group['objects'][0];
            foreach ($group['objects'] as $inv) {
                $inv->setState(InvalidationInterface::PROCESSING);
            }
            $token_data = ['invalidation' => $invalidation];
            $uri = $this->getUri($token_data);
            $opt = $this->getOptions($token_data);
            $tags = new CacheTagsHeaderValue($group['expression'], Hash::cacheTags($group['expression']));
            $exp = 'obj.http.Section-Cache-Tags ~ "(' . str_replace(' ','|',$tags) . ')+"';
            //adds this at the end if this instance has a site name in the configuration, for multi-site pages.
            //the ampersands are url encoded to be %26%26 in sendReq
            $this->logger->debug("[Tag] ". count($invalidations) . " tag invalidations were bundled to be: `". $exp . "`");
            $this->sendReq($invalidation, $uri, $opt, $exp);
            foreach ($group['objects'] as $inv) {
                if ($invalidation->getState() === InvalidationInterface::SUCCEEDED) {
                    $inv->setState(InvalidationInterface::SUCCEEDED);
                } else {
                    $inv->setState(InvalidationInterface::FAILED);
                }
            }
        }
    }
  
    /**
     * invalidateEverything($invalidations)
     * This will use obj.status != 0 to ban every page that does not have an empty response
     * @param array $invalidations
     * This takes in an array of Invalidation, and only make one purge because it only needs to ban everything once.
     * @return void
     */
    public function invalidateEverything(array $invalidations)
    {
        //invalidates everything within the siteName;
        $globalExpression = "obj.status != 0";
        $invalidation = $invalidations[0];
        //Only make one request, but if there are multiple everything invalidations queued then it will make sure all of them get marked appropriately.
        foreach ($invalidations as $inv) {
            $inv->setState(InvalidationInterface::PROCESSING);
        }
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
        $this->sendReq($invalidation, $uri, $opt, $exp);
        foreach ($invalidations as $inv) {
            if ($invalidation->getState() === InvalidationInterface::SUCCEEDED) {
                $inv->setState(InvalidationInterface::SUCCEEDED);
            } else {
                $inv->setState(InvalidationInterface::FAILED);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateURLs(array $invalidations)
    {
        $this->logger->debug("[Domain] section does not support bundling URL purges but will pass this queue item on to the non-bundled purger.");
        parent::invalidateURLs($invalidations);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateDomain(array $invalidations)
    {
        $this->logger->debug("[Domain] section does not support bundling domain purges but will pass this queue item on to the non-bundled purger.");
        parent::invalidateDomain($invalidations);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateWildcardUrls(array $invalidations)
    {
        $this->logger->debug("[Wildcard URLs] section does not support bundling URL purges but will pass this queue item on to the non-bundled purger.");
        parent::invalidateWildcardUrls($invalidations);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidatePaths(array $invalidations)
    {
        $this->logger->debug("[Paths] section does not currently support bundling path purges but will pass this queue item on to the non-bundled purger.");
        parent::invalidatePaths($invalidations);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateWildcardPaths(array $invalidations)
    {
        $this->logger->debug("[Paths] section does not currently support bundling path purges but will pass this queue item on to the non-bundled purger.");
        parent::invalidateWildcardPaths($invalidations);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateRegex(array $invalidations)
    {
        $this->logger->debug("[Regex] section does not support bundling regex purges (as varnish doesn't support the || operator on ban requests, and would require making very large regex queries that would pin CPU usage) but will pass this queue item on to the non-bundled purger.");
        parent::invalidateRegex($invalidations);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateRawExpression(array $invalidations)
    {
        $this->logger->debug("[Raw] section does not support bundling raw purges (as varnish doesn't support the || operator on ban requests) but will pass this queue item on to the non-bundled purger.");
        parent::invalidatePaths($invalidations);
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
    public function routeTypeToMethod($type)
    {
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
