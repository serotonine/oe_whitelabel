<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_whitelabel_paragraphs\Kernel\PatternAssertions;

use Drupal\file\Entity\File;
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class for asserting Listing pattern.
 */
class ListingAssertion extends Assert {

  /**
   * Assert default variant of Listing is rendering correctly.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DomCrawler where to check the element.
   * @param \Drupal\file\Entity\File $file
   *
   *   Image file added to the list item.
   */
  public function assertDefaultListingRendering(Crawler $crawler, File $file): void {
    $this->assertCount(6, $crawler->filter('article.listing-item.card'));
    $this->assertCount(6, $crawler->filter('.bcl-card-start-col'));
    $this->assertCount(6, $crawler->filter('div.card-body'));
    $text_element = $crawler->filter('div.card-text');
    $this->assertCount(6, $text_element);
    $this->assertImageRendering($crawler, $file);
  }

  /**
   * Assert highlight variant of Listing is rendering correctly.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DomCrawler where to check the element.
   * @param \Drupal\file\Entity\File $file
   *   Image file added to the list item.
   */
  public function assertHighlightListingRendering(Crawler $crawler, File $file): void {
    $this->assertCount(6, $crawler->filter('article.listing-item--highlight.border-0.bg-lighter.card'));
    $text_element = $crawler->filter('div.card-text');
    $this->assertCount(6, $text_element);
    $this->assertImageRendering($crawler, $file);
  }

  /**
   * Assert Listing is rendering correctly.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DomCrawler where to check the element.
   * @param int $nid
   *   Node identifier.
   */
  public function assertListingRendering(Crawler $crawler, int $nid): void {
    $this->assertCount(1, $crawler->filter('div.bcl-listing'));
    $this->assertCount(6, $crawler->filter('div.row-cols-1.g-4 > div.col'));
    $this->assertStringContainsString('Listing item block title', trim($crawler->filter('h2.bcl-heading')->text()));
    $this->assertCount(6, $crawler->filter('h1.card-title'));
    $link_element = $crawler->filter('a.standalone');
    $this->assertCount(6, $link_element);
    $this->assertStringContainsString(
      'node/' . $nid,
      $link_element->attr('href')
    );
    $text_element = $crawler->filter('div.card-text');
    $this->assertStringContainsString('Item title 1', trim($crawler->filter('h1.card-title > a.standalone')->text()));
    $this->assertStringContainsString('Label 1 - 1', trim($crawler->filter('span.badge')->eq(0)->text()));
    $this->assertStringContainsString('Label 2 - 1', trim($crawler->filter('span.badge')->eq(1)->text()));
    $this->assertStringContainsString(
      'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus ut ex tristique, dignissim sem ac, bibendum est.',
      $text_element->text()
    );
  }

  /**
   * Assert image is rendering.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The DomCrawler where to check the element.
   * @param \Drupal\file\Entity\File $file
   *   Image file added to the card.
   */
  public function assertImageRendering(Crawler $crawler, File $file): void {
    $image_element = $crawler->filter('.card-img-top');
    $this->assertCount(6, $image_element);
    $this->assertStringContainsString(
      \Drupal::service('file_url_generator')->generateString($file->getFileUri()),
      $image_element->attr('src')
    );
    $this->assertStringContainsString(
      'Alt for image 1',
      $image_element->attr('alt')
    );
  }

}
