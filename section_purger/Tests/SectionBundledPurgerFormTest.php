<?php

namespace Drupal\section_purger\Tests;

use Drupal\section_purger\Tests\SectionPurgerFormTestBase;

/**
 * Tests \Drupal\section_purger\Form\SectionBundledPurgerForm.
 *
 * @group section_purger
 */
class SectionBundledPurgerFormTest extends SectionPurgerFormTestBase {

  /**
   * The full class of the form being tested.
   *
   * @var string
   */
  protected $formClass = 'Drupal\section_purger\Form\SectionBundledPurgerForm';

  /**
   * The plugin ID for which the form tested is rendered for.
   *
   * @var string
   */
  protected $plugin = 'httpbundled';

  /**
   * The token group names the form is supposed to display.
   *
   * @var string[]
   *
   * @see purge_tokens_token_info()
   */
  protected $tokenGroups = ['invalidations'];

}
