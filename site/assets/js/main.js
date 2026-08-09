/**
 * main.js — Pill-фильтры, sticky nav highlight, IntersectionObserver fadeIn, TOC
 * Точка Плавления
 */

document.addEventListener('DOMContentLoaded', () => {

  // ── 1. PILL FILTER ──────────────────────────────────────────
  const filterPills = document.querySelectorAll('[data-filter]');
  const filterCards = document.querySelectorAll('[data-cat]');

  if (filterPills.length && filterCards.length) {
    filterPills.forEach(pill => {
      pill.addEventListener('click', () => {
        filterPills.forEach(p => p.classList.remove('active'));
        pill.classList.add('active');

        const cat = pill.getAttribute('data-filter');
        filterCards.forEach(card => {
          const cardCat = card.getAttribute('data-cat');
          const show = cat === 'all' || cardCat === cat;
          card.style.display = show ? '' : 'none';
          if (show) {
            card.style.animation = 'none';
            card.offsetHeight; // reflow
            card.style.animation = 'fade-in-up 0.35s ease forwards';
          }
        });
      });
    });
  }

  // ── 2. STICKY NAV ACTIVE LINK (scroll spy) ─────────────────
  const sections = document.querySelectorAll('section[id]');
  const navLinks  = document.querySelectorAll('.nav-links a');

  if (sections.length && navLinks.length) {
    const spyObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          navLinks.forEach(link => {
            const href = link.getAttribute('href');
            const matches = href && href.includes('#' + entry.target.id);
            link.classList.toggle('active', matches);
          });
        }
      });
    }, { rootMargin: '-40% 0px -50% 0px' });

    sections.forEach(s => spyObserver.observe(s));
  }

  // ── 3. INTERSECTIONOBSERVER FADE-IN ─────────────────────────
  const fadeEls = document.querySelectorAll('.article-card, .icard, .article-row');
  if (fadeEls.length) {
    const fadeObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('fade-in');
          fadeObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    fadeEls.forEach(el => {
      el.style.opacity = '0';
      fadeObserver.observe(el);
    });
  }

  // ── 4. TOC AUTO-GENERATION ──────────────────────────────────
  const articleBody = document.querySelector('.article-body');
  const tocList     = document.querySelector('.toc-list');

  if (articleBody && tocList) {
    const headings = articleBody.querySelectorAll('h2, h3');
    const tocItems = [];

    headings.forEach((heading, i) => {
      if (!heading.id) {
        heading.id = 'section-' + i;
      }
      const level = heading.tagName === 'H2' ? 'toc-h2' : 'toc-h3';
      const li  = document.createElement('li');
      li.className = level;
      const a   = document.createElement('a');
      a.href    = '#' + heading.id;
      a.textContent = heading.textContent;
      li.appendChild(a);
      tocList.appendChild(li);
      tocItems.push({ el: heading, link: a });
    });

    // Highlight TOC on scroll
    if (tocItems.length) {
      const tocObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          const item = tocItems.find(t => t.el === entry.target);
          if (item) item.link.classList.toggle('active', entry.isIntersecting);
        });
      }, { rootMargin: '-20% 0px -70% 0px' });

      tocItems.forEach(t => tocObserver.observe(t.el));
    }
  }

  // ── 5. TAG CLOUD BUTTONS ────────────────────────────────────
  const tagBtns = document.querySelectorAll('.tag-btn[data-tag]');
  tagBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tagBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      // Optional: filter .article-row by data-cat
      const tag = btn.getAttribute('data-tag');
      const rows = document.querySelectorAll('.article-row[data-cat]');
      rows.forEach(row => {
        row.style.display = (tag === 'all' || row.getAttribute('data-cat') === tag) ? '' : 'none';
      });
    });
  });

});
