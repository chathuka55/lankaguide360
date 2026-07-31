// LankaGuide 360 — shared UI behaviour
document.addEventListener('DOMContentLoaded', () => {
  // Toggle styling for interest chip checkboxes on the trip planner.
  // The checkbox is nested inside the <label>, so the browser already
  // toggles it natively on click — we only need to keep the visual
  // "active" class in sync with the checkbox's real state via 'change'.
  document.querySelectorAll('.interest-chip').forEach(chip => {
    const input = chip.querySelector('input');
    if (!input) return;
    const sync = () => chip.classList.toggle('active', input.checked);
    sync();
    input.addEventListener('change', sync);
  });

  // Smooth-scroll for on-page anchors
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', (e) => {
      const target = document.querySelector(a.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
});
