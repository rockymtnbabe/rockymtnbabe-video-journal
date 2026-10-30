/* One reusable modal for all RMB Video Journal shortcode grids. */
(function () {
  'use strict';
  function init() {
    if (!document.querySelector('.rmb-video-grid')) return;
    // Elementor may render several galleries; one shared modal is intentional.
    if (document.getElementById('rmb-catalog-lightbox')) return;
    const box = document.createElement('div');
    box.id = 'rmb-catalog-lightbox';
    box.className = 'rmb-video-lightbox';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-label', 'Video player');
    box.setAttribute('aria-hidden', 'true');
    box.setAttribute('inert', '');
    box.innerHTML = '<button type="button" class="rmb-video-close" aria-label="Close video">×</button><video controls playsinline preload="none"></video>';
    document.body.appendChild(box);
    const video = box.querySelector('video');
    const closeButton = box.querySelector('.rmb-video-close');
    let oldFocus = null, scrollY = 0, oldTop = '', oldLeft = '', oldRight = '', oldWidth = '';
    function open(url) {
      if (box.classList.contains('is-open')) return;
      oldFocus = document.activeElement;
      scrollY = window.scrollY;
      oldTop = document.body.style.top;
      oldLeft = document.body.style.left;
      oldRight = document.body.style.right;
      oldWidth = document.body.style.width;
      document.body.style.top = '-' + scrollY + 'px';
      document.body.style.left = '0';
      document.body.style.right = '0';
      document.documentElement.classList.add('rmb-video-lock');
      document.body.classList.add('rmb-video-body-lock');
      box.removeAttribute('inert');
      box.classList.add('is-open');
      box.setAttribute('aria-hidden', 'false');
      video.src = url;
      closeButton.focus();
      video.play().catch(function () {});
    }
    function close() {
      if (!box.classList.contains('is-open')) return;
      video.pause();
      video.removeAttribute('src');
      video.load();
      box.classList.remove('is-open');
      box.setAttribute('aria-hidden', 'true');
      // Restore focus before making dialog inert.
      if (oldFocus && oldFocus.isConnected) oldFocus.focus({ preventScroll: true });
      box.setAttribute('inert', '');
      document.documentElement.classList.remove('rmb-video-lock');
      document.body.classList.remove('rmb-video-body-lock');
      document.body.style.top = oldTop;
      document.body.style.left = oldLeft;
      document.body.style.right = oldRight;
      document.body.style.width = oldWidth;
      window.scrollTo(0, scrollY);
    }
    document.addEventListener('click', function (event) {
      const btn = event.target.closest('.rmb-video-grid [data-video]');
      if (btn) { event.preventDefault(); open(btn.dataset.video); }
    });
    closeButton.addEventListener('click', close);
    box.addEventListener('click', function (event) { if (event.target === box) close(); });
    document.addEventListener('keydown', function (event) {
      if (!box.classList.contains('is-open')) return;
      if (event.key === 'Escape') { event.preventDefault(); close(); return; }
      if (event.key !== 'Tab') return;
      const items = [closeButton, video];
      const first = items[0], last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      else if (!box.contains(document.activeElement)) { event.preventDefault(); first.focus(); }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
