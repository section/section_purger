<?php

namespace Drupal\section_purger\Plugin\Purge\Purger;

use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\section_purger\Plugin\Purge\Purger\SectionPurgerBase;

/**
 * Section Purger.
 *
 * @PurgePurger(
 *   id = "Section Purger",
 *   label = @Translation("Section Purger"),
 *   configform = "\Drupal\section_purger\Form\SectionPurgerForm",
 *   cooldown_time = 0.0,
 *   description = @Translation("Purger that sends invalidation expressions from your Drupal instance to the Section platform."),
 *   multi_instance = TRUE,
 *   types = {},
 * )
 */
class SectionPurger extends SectionPurgerBase implements PurgerInterface {

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {

    // Iterate every single object and fire a request per object.
    foreach ($invalidations as $invalidation) {
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      $uri = $uri . $opt["headers"]["purge-cache-tags"];

      if ($this->getSiteName()) {
        $uri = $uri . " %26%26 req.http.host == " . $this->getSiteName();
      }

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
        $this->logger()->emergency("item failed due @e, details (JSON): @debug",
          ['@e' => get_class($e), '@debug' => $debug]
        );
      }
    }
  }

}
