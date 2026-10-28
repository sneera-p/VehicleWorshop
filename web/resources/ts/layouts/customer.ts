import { start } from "@sneze/x-attributes";

start();

/**
 * Customer Layout TypeScript
 * Controls mobile drawer, sub-navbar interactions, and dropdowns.
 */

function initMobileDrawer(): void {
   const toggleBtn = document.getElementById('sidebar-toggle-btn');
   const closeBtn = document.getElementById('sidebar-close-btn');
   const sidebar = document.getElementById('portal-sidebar');
   const backdrop = document.getElementById('mobile-backdrop');

   if (!sidebar || !backdrop) return;

   const openSidebar = (): void => {
      sidebar.classList.add('sidebar-open');
      backdrop.classList.add('active');
      document.body.style.overflow = 'hidden';
   };

   const closeSidebar = (): void => {
      sidebar.classList.remove('sidebar-open');
      backdrop.classList.remove('active');
      document.body.style.overflow = '';
   };

   if (toggleBtn) {
      toggleBtn.addEventListener('click', (e) => {
         e.stopPropagation();
         openSidebar();
      });
   }

   if (closeBtn) {
      closeBtn.addEventListener('click', (e) => {
         e.stopPropagation();
         closeSidebar();
      });
   }

   backdrop.addEventListener('click', () => {
      closeSidebar();
   });

   // Close on escape key
   document.addEventListener('keydown', (e: KeyboardEvent) => {
      if (e.key === 'Escape' && sidebar.classList.contains('sidebar-open')) {
         closeSidebar();
      }
   });
}

document.addEventListener('DOMContentLoaded', () => {
   initMobileDrawer();
});
