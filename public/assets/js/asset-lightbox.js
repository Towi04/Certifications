/**
 * Lightbox flotante para visuales (imagen / YouTube) con navegación.
 */
(function () {
  var root = document.getElementById('assetLightbox');
  if (!root) return;

  var items = [];
  var index = 0;
  var img = root.querySelector('.asset-lightbox__image');
  var video = root.querySelector('.asset-lightbox__video');
  var caption = root.querySelector('.asset-lightbox__caption');
  var counter = root.querySelector('.asset-lightbox__counter');
  var prevBtn = root.querySelector('[data-lightbox-prev]');
  var nextBtn = root.querySelector('[data-lightbox-next]');

  function collectItems() {
    var gallery = document.querySelector('[data-asset-gallery]');
    if (!gallery) return [];
    try {
      var raw = gallery.getAttribute('data-lightbox-items') || '[]';
      var parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function show(i) {
    if (!items.length) return;
    index = ((i % items.length) + items.length) % items.length;
    var item = items[index];
    caption.textContent = item.title || '';
    counter.textContent = (index + 1) + ' / ' + items.length;

    if (item.type === 'youtube') {
      img.hidden = true;
      img.removeAttribute('src');
      video.hidden = false;
      video.src = item.src;
    } else {
      video.hidden = true;
      video.removeAttribute('src');
      img.hidden = false;
      img.src = item.src;
      img.alt = item.title || '';
    }

    var multi = items.length > 1;
    if (prevBtn) prevBtn.hidden = !multi;
    if (nextBtn) nextBtn.hidden = !multi;
  }

  function open(i) {
    items = collectItems();
    if (!items.length) return;
    root.hidden = false;
    document.body.classList.add('asset-lightbox-open');
    show(i);
  }

  function close() {
    root.hidden = true;
    document.body.classList.remove('asset-lightbox-open');
    if (video) {
      video.removeAttribute('src');
      video.hidden = true;
    }
    if (img) {
      img.removeAttribute('src');
      img.hidden = true;
    }
  }

  document.querySelectorAll('[data-lightbox-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var i = parseInt(btn.getAttribute('data-lightbox-open') || '0', 10);
      open(isNaN(i) ? 0 : i);
    });
  });

  root.querySelectorAll('[data-lightbox-close]').forEach(function (el) {
    el.addEventListener('click', close);
  });
  if (prevBtn) prevBtn.addEventListener('click', function () { show(index - 1); });
  if (nextBtn) nextBtn.addEventListener('click', function () { show(index + 1); });

  document.addEventListener('keydown', function (e) {
    if (root.hidden) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') show(index - 1);
    if (e.key === 'ArrowRight') show(index + 1);
  });
})();
