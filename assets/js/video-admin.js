/* Preview the R2 URL as the category/folder or filename changes. */
(function () {
  function initialize() {
    const folder = document.getElementById('rmb_video_folder');
    const filename = document.getElementById('rmb_video_filename');
    const preview = document.getElementById('rmb_video_url_preview');
    if (!folder || !filename || !preview) return;
    const base = 'https://videos.rockymtnbabe.com/';
    function render() {
      const f = folder.value.trim();
      const n = filename.value.trim();
      preview.textContent = base + (f ? f + '/' : '') + n;
    }
    folder.addEventListener('change', render);
    filename.addEventListener('input', render);
    render();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})();
