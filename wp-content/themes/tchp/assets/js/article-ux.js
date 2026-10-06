/**
 * Точка Плавления — UX Скрипты статьи
 * Поддержка: Reading Progress, Sticky TOC Scroll-Spy, Copy-to-Clipboard, FAQ, Mobile TOC Drawer
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Полоса прогресса чтения
  const progressBar = document.getElementById('reading-progress');
  const updateProgress = () => {
    if (!progressBar) return;
    const scrollTop = window.scrollY;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
    progressBar.style.width = Math.min(100, Math.max(0, progress)) + '%';
  };
  window.addEventListener('scroll', updateProgress, { passive: true });
  updateProgress();

  // 2. Scroll-Spy для липкого оглавления (TOC)
  const tocLinks = document.querySelectorAll('.toc-link');
  const sections = [];
  tocLinks.forEach(link => {
    const targetId = link.getAttribute('href');
    if (targetId && targetId.startsWith('#')) {
      const el = document.querySelector(targetId);
      if (el) sections.push({ id: targetId.substring(1), element: el, link: link });
    }
  });

  const onScrollTOC = () => {
    if (sections.length === 0) return;
    const scrollPos = window.scrollY + 140;
    let currentActive = null;

    for (let i = 0; i < sections.length; i++) {
      const top = sections[i].element.offsetTop;
      if (scrollPos >= top) {
        currentActive = sections[i];
      } else {
        break;
      }
    }

    tocLinks.forEach(l => l.classList.remove('active'));
    if (currentActive) {
      currentActive.link.classList.add('active');
    } else if (sections.length > 0) {
      sections[0].link.classList.add('active');
    }
  };

  window.addEventListener('scroll', onScrollTOC, { passive: true });
  onScrollTOC();

  // 3. Копирование параметров и ссылки с Toast уведомлением
  const toast = document.getElementById('copy-toast');
  const showToast = (message = 'Скопировано в буфер!') => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(window._toastTimeout);
    window._toastTimeout = setTimeout(() => {
      toast.classList.remove('show');
    }, 2500);
  };

  document.querySelectorAll('[data-copy]').forEach(el => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      const text = el.getAttribute('data-copy') || el.innerText.trim();
      navigator.clipboard.writeText(text).then(() => {
        showToast(`Параметр "${text}" скопирован!`);
      }).catch(() => {
        showToast('Не удалось скопировать');
      });
    });
  });

  const shareBtn = document.getElementById('btn-share-link');
  if (shareBtn) {
    shareBtn.addEventListener('click', (e) => {
      e.preventDefault();
      navigator.clipboard.writeText(window.location.href).then(() => {
        showToast('Ссылка на статью скопирована!');
      });
    });
  }

  // 4. FAQ Аккордеон
  document.querySelectorAll('.faq-header').forEach(header => {
    header.addEventListener('click', () => {
      const item = header.closest('.faq-item');
      if (item) {
        const isOpen = item.classList.contains('open');
        // Закрываем остальные при желании
        document.querySelectorAll('.faq-item').forEach(i => {
          if (i !== item) i.classList.remove('open');
        });
        item.classList.toggle('open', !isOpen);
      }
    });
  });

  // 5. Мобильное оглавление (Шторка)
  const mobileTocFab = document.getElementById('mobile-toc-fab');
  const mobileTocDrawer = document.getElementById('mobile-toc-drawer');
  const mobileTocClose = document.getElementById('mobile-toc-close');

  if (mobileTocFab && mobileTocDrawer) {
    mobileTocFab.addEventListener('click', () => {
      mobileTocDrawer.classList.toggle('hidden');
    });
    if (mobileTocClose) {
      mobileTocClose.addEventListener('click', () => {
        mobileTocDrawer.classList.add('hidden');
      });
    }
    mobileTocDrawer.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => {
        mobileTocDrawer.classList.add('hidden');
      });
    });
  }
});
