<?php

namespace Drupal\section_purger\Form;

use Drupal\section_purger\Form\SectionPurgerFormBase;

/**
 * Configuration form for the HTTP Bundled Purger.
 */
class SectionBundledPurgerForm extends SectionPurgerFormBase {

  /**
   * The token group names this purger supports replacing tokens for.
   *
   * @var string[]
   *
   * @see purge_tokens_token_info()
   */
  protected $tokenGroups = ['invalidations'];

}
