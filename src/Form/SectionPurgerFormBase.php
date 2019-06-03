<?php

namespace Drupal\section_purger\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface;
use Drupal\purge_ui\Form\PurgerConfigFormBase;
use Drupal\section_purger\Entity\SectionPurgerSettings;

/**
 * Abstract form base for HTTP based configurable purgers.
 */
abstract class SectionPurgerFormBase extends PurgerConfigFormBase {

  /**
   * The service that generates invalidation objects on-demand.
   *
   * @var \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface
   */
  protected $purgeInvalidationFactory;

  /**
   * Static listing of all possible requests methods.
   *
   * @var array
   *
   * @todo
   *   Confirm if all relevant HTTP methods are covered.
   *   http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
   */
  protected $requestMethods = [
    'BAN',
    'GET',
    'POST',
    'HEAD',
    'PUT',
    'OPTIONS',
    'PURGE',
    'DELETE',
    'TRACE',
    'CONNECT',
  ];

  /**
   * Static listing of the possible connection schemes.
   *
   * @var array
   */
  protected $schemes = ['http', 'https'];

  /**
   * Constructs a \Drupal\section_purger\Form\ConfigurationForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface $purge_invalidation_factory
   *   The invalidation objects factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, InvalidationsServiceInterface $purge_invalidation_factory) {
    $this->setConfigFactory($config_factory);
    $this->purgeInvalidationFactory = $purge_invalidation_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('purge.invalidation.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'section_purger.configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = SectionPurgerSettings::load($this->getId($form_state));
    $form['tabs'] = ['#type' => 'vertical_tabs', '#weight' => 10];
    $this->buildFormMetadata($form, $form_state, $settings);
    $this->buildFormRequest($form, $form_state, $settings);
    $this->buildFormHeaders($form, $form_state, $settings);
    $this->buildFormPerformance($form, $form_state, $settings);
    return parent::buildForm($form, $form_state);
  }

  /**
   * Build the 'metadata' section of the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\section_purger\Entity\SectionPurgerSettings $settings
   *   Configuration entity for the purger being configured.
   */
  public function buildFormMetadata(array &$form, FormStateInterface $form_state, SectionPurgerSettings $settings) {
    $form['name'] = [
      '#title' => $this->t('Name'),
      '#type' => 'textfield',
      '#description' => $this->t('Section Purger for Drupal 8.'),
      '#default_value' => $settings->name,
      '#required' => TRUE,
    ];
    $types = [];
    foreach ($this->purgeInvalidationFactory->getPlugins() as $type => $definition) {
      $types[$type] = (string) $definition['label'];
    }
  }

  /**
   * Build the 'request' section of the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\section_purger\Entity\SectionPurgerSettings $settings
   *   Configuration entity for the purger being configured.
   */
  public function buildFormRequest(array &$form, FormStateInterface $form_state, SectionPurgerSettings $settings) {
    $form['request'] = [
      '#type' => 'details',
      '#group' => 'tabs',
      '#title' => $this->t('Request'),
      '#description' => $this->t('In this section you configure how a single HTTP request looks like.'),
    ];
    $form['request']['sitename'] = [
      '#title' => $this->t('Drupal Site Name'),
      '#type' => 'textfield',
      '#default_value' => $settings->sitename,
    ];
    $form['request']['account'] = [
      '#title' => $this->t('Account Number'),
      '#type' => 'textfield',
      '#default_value' => $settings->account,
    ];
    $form['request']['application'] = [
      '#title' => $this->t('Section Application Number'),
      '#type' => 'textfield',
      '#default_value' => $settings->application,
    ];
    $form['request']['environmentname'] = [
      '#title' => $this->t('Name of your Section environment i.e. Production, Staging, UAT'),
      '#type' => 'textfield',
      '#default_value' => $settings->environmentname,
    ];
    $form['request']['username'] = [
      '#title' => $this->t('Section Username'),
      '#type' => 'textfield',
      '#default_value' => $settings->username,
    ];
    $form['request']['password'] = [
      '#title' => $this->t('Section Password'),
      '#type' => 'password',
      '#default_value' => $settings->password,
    ];
  }

  /**
   * Build the 'headers' section of the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\section_purger\Entity\SectionPurgerSettings $settings
   *   Configuration entity for the purger being configured.
   */
  public function buildFormHeaders(array &$form, FormStateInterface $form_state, SectionPurgerSettings $settings) {
    if (is_null($form_state->get('headers_items_count'))) {
      $value = empty($settings->headers) ? 1 : count($settings->headers);
      $form_state->set('headers_items_count', $value);
    }
    $form['headers'] = [
      '#type' => 'details',
      '#group' => 'tabs',
      '#title' => $this->t('Headers'),
      '#description' => $this->t('Define the Header sent to Section'),
    ];
    $form['headers']['headers'] = [
      '#tree' => TRUE,
      '#type' => 'table',
      '#header' => [$this->t('Header'), $this->t('Value')],
      '#prefix' => '<div id="headers-wrapper">',
      '#suffix' => '</div>',
    ];
    for ($i = 0; $i < $form_state->get('headers_items_count'); $i++) {
      if (!isset($form['headers']['headers'][$i])) {
        $header = isset($settings->headers[$i]) ? $settings->headers[$i] :
          ['field' => 'Purge-Cache-Tags', 'value' => '[invalidation:expression]'];
        $form['headers']['headers'][$i]['field'] = [
          '#type' => 'textfield',
          '#default_value' => $header['field'],
          '#attributes' => ['style' => 'width: 100%;'],
        ];
        $form['headers']['headers'][$i]['value'] = [
          '#type' => 'textfield',
          '#default_value' => $header['value'],
          '#attributes' => ['style' => 'width: 100%;'],
        ];
      }
    }
  }

  /**
   * Build the 'headers' section of the form: retrieves updated elements.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildFormHeadersRebuild(array &$form, FormStateInterface $form_state) {
    return $form['headers']['headers'];
  }

  /**
   * Build the 'headers' section of the form: increments the item count.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildFormHeadersAdd(array &$form, FormStateInterface $form_state) {
    $count = $form_state->get('headers_items_count');
    $count++;
    $form_state->set('headers_items_count', $count);
    $form_state->setRebuild();
  }

  /**
   * Build the 'performance' section of the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\section_purger\Entity\SectionPurgerSettings $settings
   *   Configuration entity for the purger being configured.
   */
  public function buildFormPerformance(array &$form, FormStateInterface $form_state, SectionPurgerSettings $settings) {
    $form['performance'] = [
      '#type' => 'details',
      '#group' => 'tabs',
      '#title' => $this->t('Performance'),
    ];
    $form['performance']['cooldown_time'] = [
      '#type' => 'number',
      '#step' => 0.1,
      '#min' => 0.0,
      '#max' => 3.0,
      '#title' => $this->t('Cooldown time'),
      '#default_value' => $settings->cooldown_time,
      '#required' => TRUE,
      '#description' => $this->t('Number of seconds to wait after a group of HTTP requests (so that other purgers get fresh content)'),
    ];
    $form['performance']['max_requests'] = [
      '#type' => 'number',
      '#step' => 1,
      '#min' => 1,
      '#max' => 500,
      '#title' => $this->t('Maximum requests'),
      '#default_value' => $settings->max_requests,
      '#required' => TRUE,
      '#description' => $this->t("Maximum number of HTTP requests that can be made during Drupal's execution lifetime. Usually PHP resource restraints lower this value dynamically, but can be met at the CLI."),
    ];
    $form['performance']['runtime_measurement'] = [
      '#title' => $this->t('Runtime measurement'),
      '#type' => 'checkbox',
      '#default_value' => $settings->runtime_measurement,
    ];
    $form['performance']['runtime_measurement_help'] = [
      '#type' => 'item',
      '#states' => [
        'visible' => [
          ':input[name="runtime_measurement"]' => ['checked' => FALSE],
        ],
      ],
      '#description' => $this->t('When you uncheck this setting, capacity will be based on the sum of both timeouts. By default, capacity will automatically adjust (up and down) based on measured time data.'),
    ];
    $form['performance']['timeout'] = [
      '#type' => 'number',
      '#step' => 0.1,
      '#min' => 0.1,
      '#max' => 8.0,
      '#title' => $this->t('Timeout'),
      '#default_value' => $settings->timeout,
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="runtime_measurement"]' => ['checked' => FALSE],
        ],
      ],
      '#description' => $this->t('The timeout of the request in seconds.'),
    ];
    $form['performance']['connect_timeout'] = [
      '#type' => 'number',
      '#step' => 0.1,
      '#min' => 0.1,
      '#max' => 4.0,
      '#title' => $this->t('Connection timeout'),
      '#default_value' => $settings->connect_timeout,
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="runtime_measurement"]' => ['checked' => FALSE],
        ],
      ],
      '#description' => $this->t('The number of seconds to wait while trying to connect to a server.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Validate that our timeouts stay between the boundaries purge demands.
    $timeout = $form_state->getValue('connect_timeout') + $form_state->getValue('timeout');
    if ($timeout > 10) {
      $form_state->setErrorByName('connect_timeout');
      $form_state->setErrorByName('timeout', $this->t('The sum of both timeouts cannot be higher than 10.00 as this would affect performance too negatively.'));
    }
    elseif ($timeout < 0.4) {
      $form_state->setErrorByName('connect_timeout');
      $form_state->setErrorByName('timeout', $this->t('The sum of both timeouts cannot be lower as 0.4 as this can lead to too many failures under real usage conditions.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormSuccess(array &$form, FormStateInterface $form_state) {
    $settings = SectionPurgerSettings::load($this->getId($form_state));

    // Empty 'body' when 'show_body_form' isn't checked.
    if ($form_state->getValue('show_body_form') === 0) {
      $form_state->setValue('body', '');
    }

    // Rewrite 'headers' so that it contains the exact right format for CMI.
    if (!is_null($submitted_headers = $form_state->getValue('headers'))) {
      $headers = [];
      foreach ($submitted_headers as $header) {
        if (strlen($header['field'] && strlen($header['value']))) {
          $headers[] = $header;
        }
      }
      $form_state->setValue('headers', $headers);
    }

    // Rewrite 'scheme' and 'request_method' to have the right CMI values.
    if (!is_null($scheme = $form_state->getValue('scheme'))) {
      $form_state->setValue('scheme', $this->schemes[$scheme]);
    }
    if (!is_null($method = $form_state->getValue('request_method'))) {
      $form_state->setValue('request_method', $this->requestMethods[$method]);
    }

    // Iterate the config object and overwrite values found in the form state.
    foreach ($settings as $key => $default_value) {
      if (!is_null($value = $form_state->getValue($key))) {
        $settings->$key = $value;
      }
    }
    $settings->save();
  }

}
