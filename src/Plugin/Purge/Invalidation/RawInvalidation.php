<?php

namespace Drupal\section_purger\Plugin\Purge\Invalidation;

use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationBase;

/**
 * Describes invalidation by raw varnish ban expression, e.g.: 'req.url ~ \.(jpg|jpeg|css|js)$'.
 *
 * @PurgeInvalidation(
 *   id = "raw",
 *   label = @Translation("Raw expression"),
 *   description = @Translation("Invalidates by raw varnish ban expression."),
 *   examples = {"req.url ~ (jpg|jpeg|css|js)$"},
 *   expression_required = TRUE,
 *   expression_can_be_empty = FALSE,
 *   expression_must_be_string = TRUE
 * )
 */
class RawInvalidation extends InvalidationBase implements InvalidationInterface {}
 