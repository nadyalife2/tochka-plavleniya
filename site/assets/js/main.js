/* Точка плавления — main.js */

document.addEventListener('DOMContentLoaded', () => {

  /* 1. Pill-фильтры статей */
  const filterBtns = document.querySelectorAll('[data-filter]');
  const cards = document.querySelectorAll('[data-tag]');
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const tag = btn.dataset.filter;
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      cards.forEach(card => {
        const match = tag === 'all' || card.dataset.tag === tag;
        card.style.display = match ? '' : 'none';
      });
    });
  });

  /* 2. Авто-оглавление TOC из h2/h3 */
  const tocList = document.querySelector('.toc__list');
  if (tocList) {
    const headings = document.querySelectorAll('.prose h2, .prose h3');
    headings.forEach((h, i) => {
      if (!h.id) h.id = 'heading-' + i;
      const li = document.createElement('li');
      li.className = 'toc__item';
      li.style.paddingLeft = h.tagName === 'H3' ? '1rem' : '0';
      li.innerHTML = `<a href="#${h.id}">${h.textContent}</a>`;
      tocList.appendChild(li);
    });

    /* Подсветка активного пункта при скролле */
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        const link = tocList.querySelector(`a[href="#${entry.target.id}"]`);
        if (link) link.classList.toggle('active', entry.isIntersecting);
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    headings.forEach(h => observer.observe(h));
  }

  /* 3. Sticky NAV — активный пункт при скролле */
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav__links a[href^="#"]');
  const sectionObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        navLinks.forEach(a => a.classList.remove('active'));
        const link = document.querySelector(`.nav__links a[href="#${entry.target.id}"]`);
        if (link) link.classList.add('active');
      }
    });
  }, { threshold: 0.3 });
  sections.forEach(s => sectionObserver.observe(s));

  /* 4. Анимации входа IntersectionObserver */
  const animEls = document.querySelectorAll('.anim-in');
  const animObserver = new IntersectionObserver(entries => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        entry.target.style.animationDelay = (i * 0.08) + 's';
        entry.target.classList.add('visible');
        animObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });
  animEls.forEach(el => animObserver.observe(el));

  /* 5. Distraction-free / focus mode */
  const focusBtn = document.querySelector('.focus-toggle');
  if (focusBtn) {
    focusBtn.addEventListener('click', () => {
      document.body.classList.toggle('focus-mode');
      focusBtn.textContent = document.body.classList.contains('focus-mode')
        ? '[ выйти из фокуса ]'
        : '[ режим фокуса ]';
    });
  }

  /* 6. Mobile nav toggle */
  const navToggle = document.querySelector('.nav__toggle');
  const navMenu = document.querySelector('.nav__links');
  if (navToggle && navMenu) {
    navToggle.addEventListener('click', () => navMenu.classList.toggle('open'));
  }

});