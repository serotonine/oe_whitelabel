<?php

declare(strict_types = 1);

namespace Drupal\oe_whitelabel_helper\Plugin\Field\FieldFormatter;

use CommerceGuys\Addressing\Locale;
use Drupal\address\AddressInterface;
use Drupal\address\LabelHelper;
use Drupal\address\Plugin\Field\FieldFormatter\AddressDefaultFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Format an address inline with locale format and a configurable separator.
 *
 * @FieldFormatter(
 *   id = "oe_whitelabel_helper_address_inline",
 *   label = @Translation("Inline address"),
 *   field_types = {
 *     "address",
 *   },
 * )
 *
 * @see https://github.com/openeuropa/oe_theme/blob/3.x/modules/oe_theme_helper/src/Plugin/Field/FieldFormatter/AddressInlineFormatter.php
 */
class AddressInlineFormatter extends AddressDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'delimiter' => ', ',
      'properties' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['delimiter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delimiter'),
      '#default_value' => $this->getSetting('delimiter'),
      '#description' => $this->t('Specify delimiter between address items.'),
      '#required' => TRUE,
    ];

    $form['properties'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Properties'),
      '#default_value' => array_keys(array_filter($this->getSetting('properties'))),
      '#description' => $this->t('Which properties should be displayed. Leave empty for all.'),
      '#options' => $this->getPropertiesDisplayOptions(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [
      $this->t('Delimiter: @delimiter', [
        '@delimiter' => $this->getSetting('delimiter'),
      ]),
      $this->t('Properties: @properties', [
        '@properties' => implode(', ', array_keys(array_filter($this->getSetting('properties')))),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewElement($item, $langcode);
    }

    return $elements;
  }

  /**
   * Builds a renderable array for a single address item.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array.
   */
  protected function viewElement(AddressInterface $address, $langcode) {
    $country_code = $address->getCountryCode();
    $countries = $this->countryRepository->getList($langcode);
    $address_format = $this->addressFormatRepository->get($country_code);
    $values = $this->getValues($address, $address_format);

    $address_elements['%country'] = $countries[$country_code];
    foreach ($address_format->getUsedFields() as $field) {
      $address_elements['%' . $field] = $values[$field];
    }

    if (Locale::matchCandidates($address_format->getLocale(), $address->getLocale())) {
      $format_string = '%country' . "\n" . $address_format->getLocalFormat();
    }
    else {
      $format_string = $address_format->getFormat() . "\n" . '%country';
    }

    /*
     * Remove extra characters from address format since address fields are
     * optional.
     *
     * @see \CommerceGuys\Addressing\AddressFormat\AddressFormatRepository::getDefinitions()
     */
    $format_string = str_replace([',', ' - ', '/'], "\n", $format_string);
    $format_string = $this->alterFormatString($format_string);

    $items = $this->extractAddressItems($format_string, $address_elements);

    return [
      '#theme' => 'oe_whitelabel_helper_address_inline',
      '#address' => $address,
      '#address_items' => $items,
      '#address_delimiter' => $this->getSetting('delimiter'),
      '#cache' => [
        'contexts' => [
          'languages:' . LanguageInterface::TYPE_INTERFACE,
        ],
      ],
    ];
  }

  /**
   * Extract address items from a format string and replace placeholders.
   *
   * @param string $string
   *   The address format string, containing placeholders.
   * @param array $replacements
   *   An array of address items.
   *
   * @return array
   *   The exploded lines.
   */
  protected function extractAddressItems(string $string, array $replacements): array {
    $properties = array_map(function (string $property): string {
      return '%' . $property;
    }, array_keys(array_filter($this->getSetting('properties'))));

    // Make sure the replacements don't have any unneeded newlines.
    $replacements = array_map('trim', $replacements);

    if (!empty($properties)) {
      foreach ($replacements as $key => &$value) {
        if (!in_array($key, $properties)) {
          $value = '';
        }
      }
    }

    $string = strtr($string, $replacements);
    // Remove noise caused by empty placeholders.
    $lines = explode("\n", $string);

    foreach ($lines as $index => $line) {
      // Remove leading punctuation, excess whitespace.
      $line = trim(preg_replace('/^[-,]+/', '', $line, 1));
      $line = preg_replace('/\s\s+/', ' ', $line);
      $lines[$index] = $line;
    }
    // Remove empty lines.
    $lines = array_filter($lines);

    return $lines;
  }

  /**
   * Provides the options for the properties display setting.
   *
   * @return array
   *   The properties display options.
   */
  protected function getPropertiesDisplayOptions(): array {
    return [
      'country' => $this->t('The country'),
    ] + LabelHelper::getGenericFieldLabels();
  }

  /**
   * Alters the format string depending on fields options selected.
   *
   * @param string $format_string
   *   The format string.
   *
   * @return string
   *   The altered format string.
   */
  protected function alterFormatString(string $format_string): string {
    $options_selected = array_keys(array_filter($this->getSetting('properties')));
    if (empty($options_selected)) {
      return $format_string;
    }
    $options_list = array_keys($this->getPropertiesDisplayOptions());
    // Negate the selected options against the list. The format can be complex
    // with spaces, newlines and other artifacts, the best strategy is to get
    // the properties to remove and then string replace.
    $fields = array_diff($options_list, $options_selected);
    // Prepend '%' to all items, this is how the properties are represented in
    // the format.
    $fields = array_map(function (string $field): string {
      return '%' . $field;
    }, $fields);

    return str_replace($fields, '', $format_string);
  }

}
