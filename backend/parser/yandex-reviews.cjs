#!/usr/bin/env node
/**
 * Headless-browser fallback парсера отзывов Яндекс.Карт.
 *
 * Запускается из PHP (App\Services\Parser\BrowserYandexMapsParser) и печатает
 * в stdout единственный JSON-объект:
 *
 *   {
 *     "externalId": "1234567890",
 *     "title": "...", "address": "...",
 *     "rating": 4.7, "ratingsCount": 1250, "reviewsCount": 612,
 *     "reviews": [ { "externalId", "authorName", "reviewDate", "text", "rating" } ]
 *   }
 *
 * При ошибке печатает { "error": "blocked|empty|markup|invalid_url|<текст>" }.
 *
 * Селекторы Яндекс.Карт нестабильны — они вынесены сюда (а не размазаны по
 * проекту), снабжены fallback-вариантами и подробно описаны в README.
 */
const { chromium } = require('playwright');

function arg(name, fallback) {
  const prefix = `--${name}=`;
  const found = process.argv.find((a) => a.startsWith(prefix));
  return found ? found.slice(prefix.length) : fallback;
}

const URL = arg('url', '');
const MAX_REVIEWS = parseInt(arg('max-reviews', '700'), 10);
const SCROLL_DELAY = parseInt(arg('scroll-delay', '500'), 10);

function out(obj) {
  process.stdout.write(JSON.stringify(obj));
}

(async () => {
  if (!URL) {
    out({ error: 'invalid_url' });
    process.exit(0);
  }

  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });

  try {
    const context = await browser.newContext({
      locale: 'ru-RU',
      userAgent:
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 ' +
        '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
      viewport: { width: 1280, height: 1600 },
    });
    const page = await context.newPage();

    await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 45000 });

    // Признаки капчи / блокировки.
    const html = await page.content();
    if (/SmartCaptcha|showcaptcha|captcha-page|Доступ ограничен/i.test(html)) {
      out({ error: 'blocked' });
      return;
    }

    // Контейнер ленты отзывов.
    const listSelector = '.business-reviews-card-view__reviews-container, [class*="reviews-card-view__review"]';
    try {
      await page.waitForSelector(listSelector, { timeout: 15000 });
    } catch {
      out({ error: 'empty' });
      return;
    }

    // Один элемент-обёртка на каждый отзыв в ленте (не вложенные подэлементы).
    const cardSelector = '.business-reviews-card-view__review';

    // Прокрутка ленты, пока появляются новые отзывы или не достигнут лимит.
    let previous = 0;
    let stagnant = 0;
    for (let i = 0; i < 400 && stagnant < 4; i++) {
      const count = await page.locator(cardSelector).count();
      if (count >= MAX_REVIEWS) break;
      if (count === previous) stagnant++;
      else stagnant = 0;
      previous = count;

      await page.evaluate((sel) => {
        const el = document.querySelector(sel);
        if (el) el.scrollBy(0, el.scrollHeight);
        window.scrollTo(0, document.body.scrollHeight);
      }, listSelector);
      await page.waitForTimeout(SCROLL_DELAY);
    }

    const data = await page.evaluate(
      ({ max, cardSel }) => {
        const text = (el) => (el ? el.textContent.trim() : null);
        // schema.org-разметка карточки: значения чистые и парсятся однозначно.
        const metaContent = (root, prop) => {
          const el = root.querySelector(`meta[itemprop="${prop}"]`);
          return el ? el.getAttribute('content') : null;
        };

        const ratingMeta = metaContent(document, 'ratingValue');
        const ratingBadge = document.querySelector('[class*="business-summary-rating-badge-view__rating-text"]');
        const ratingRaw =
          ratingMeta ??
          (ratingBadge ? ratingBadge.textContent.replace(',', '.').replace(/[^\d.]/g, '') : null);

        const ratingsCountMeta = metaContent(document, 'ratingCount');
        const reviewsCountMeta = metaContent(document, 'reviewCount');
        const reviewsTextMatch = document.body.innerText.match(/(\d[\d\s]*)\s*отзыв/);

        const cards = Array.from(document.querySelectorAll(cardSel)).slice(0, max);
        const reviews = cards.map((card) => {
          const author =
            card.querySelector('[itemprop="name"]') ||
            card.querySelector('[class*="business-review-view__author-name"]');
          const body =
            card.querySelector('[itemprop="reviewBody"]') ||
            card.querySelector('[class*="business-review-view__body-text"]');
          // Дата: предпочитаем ISO из meta[datePublished], иначе видимый текст.
          const dateIso = metaContent(card, 'datePublished');
          const dateText = card.querySelector('[class*="business-review-view__date"]');
          // Рейтинг отзыва: meta ratingValue внутри карточки, иначе число «полных» звёзд.
          const ratingInCard = metaContent(card, 'ratingValue');
          const fullStars = card.querySelectorAll('[class*="business-rating-badge-view__star"][class*="_full"]').length;

          return {
            externalId: card.getAttribute('data-review-id') || null,
            authorName: author ? author.textContent.trim() : null,
            text: body ? body.textContent.trim() : null,
            reviewDate: dateIso || (dateText ? dateText.textContent.trim() : null),
            rating: ratingInCard ? parseInt(ratingInCard, 10) : fullStars || null,
          };
        });

        return {
          title: text(document.querySelector('h1')),
          address: text(document.querySelector('[class*="business-contacts-view__address"]')),
          rating: ratingRaw ? parseFloat(ratingRaw) : null,
          ratingsCount: ratingsCountMeta ? parseInt(ratingsCountMeta, 10) : null,
          reviewsCount: reviewsCountMeta
            ? parseInt(reviewsCountMeta, 10)
            : reviewsTextMatch
              ? parseInt(reviewsTextMatch[1].replace(/\s/g, ''), 10)
              : null,
          reviews,
        };
      },
      { max: MAX_REVIEWS, cardSel: cardSelector },
    );

    if (data.rating === null && data.reviews.length === 0) {
      out({ error: 'markup' });
      return;
    }

    out(data);
  } catch (e) {
    out({ error: String(e && e.message ? e.message : e) });
  } finally {
    await browser.close();
  }
})();
