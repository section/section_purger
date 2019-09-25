<?php

namespace Drupal\section_purger\Plugin\Purge\TagsHeader;

use Drupal\section_purger\Entity\Hash;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderInterface;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderBase;

/**
 * Sets and formats the default response header with cache tags.
 *
 * @PurgeTagsHeader(
 *   id = "section_tagsheader",
 *   header_name = "Section-Cache-Tags",
 * )
 */
class SectionCacheTagsHeader extends TagsHeaderBase implements TagsHeaderInterface {
    
  /**
   * {@inheritdoc}
   */
  public function getValue(array $tags) {
    return new CacheTagsHeaderValue($tags, Hash::cacheTags($tags));
  }
}
