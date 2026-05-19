// ── Sidebar toggle ──────────────────────────────────────
function toggleSidebar() {
  var sidebar  = document.getElementById('sidebar');
  var overlay  = document.getElementById('sidebarOverlay');
  var isOpen   = sidebar.classList.toggle('open');
  overlay.classList.toggle('active', isOpen);
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
}

// ── Confirm dialogs & table search ──────────────────────
document.addEventListener('DOMContentLoaded', function () {
  // Confirm dialogs
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  // Live table search
  var q = document.getElementById('tableSearch');
  if (q) {
    q.addEventListener('keyup', function () {
      var v = q.value.toLowerCase();
      document.querySelectorAll('tbody tr').forEach(function (r) {
        r.style.display = r.innerText.toLowerCase().includes(v) ? '' : 'none';
      });
    });
  }
});

