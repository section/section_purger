<?php

namespace Drupal\section_purger\Tests;

use Drupal\section_purger\Tests\SectionPurgerFormTestBase;

/**
 * Tests \Drupal\section_purger\Form\SectionPurgerForm.
 *
 * @group section_purger
 */
class SectionPurgerFormTest extends SectionPurgerFormTestBase
{

  /**
   * The full class of the form being tested.
   *
   * @var string
   */
    protected $formClass = 'Drupal\section_purger\Form\SectionPurgerForm';

    /**
     * The plugin ID for which the form tested is rendered for.
     *
     * @var string
     */
    protected $plugin = 'http';

    /**
     * The token group names the form is supposed to display.
     *
     * @var string[]
     *
     * @see purge_tokens_token_info()
     */
    protected $tokenGroups = ['invalidation'];
}
