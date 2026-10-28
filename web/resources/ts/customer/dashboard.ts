/**
 * Customer Overview Dashboard TypeScript
 * Interactive progress bars and live status micro-interactions.
 */

function initProgressBar(): void {
   const progressFill = document.querySelector('.progress-fill') as HTMLElement | null;
   if (!progressFill) return;

   const targetWidth = progressFill.style.width || '65%';
   progressFill.style.width = '0%';

   // Trigger smooth CSS transition
   requestAnimationFrame(() => {
      setTimeout(() => {
         progressFill.style.width = targetWidth;
      }, 150);
   });
}

document.addEventListener('DOMContentLoaded', () => {
   initProgressBar();
});
