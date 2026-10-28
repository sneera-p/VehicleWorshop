/**
 * Password visibility toggle handler
 */
export function initPasswordToggle(): void {
   const toggleBtn = document.getElementById('toggle-password');
   const passwordInput = document.getElementById('password') as HTMLInputElement | null;
   const eyeIcon = document.getElementById('eye-icon');
   const eyeOffIcon = document.getElementById('eye-off-icon');

   if (toggleBtn && passwordInput && eyeIcon && eyeOffIcon) {
      const setPasswordVisibility = (isVisible: boolean): void => {
         passwordInput.type = isVisible ? 'text' : 'password';
         eyeIcon.classList.toggle('hidden', isVisible);
         eyeOffIcon.classList.toggle('hidden', !isVisible);
      };

      toggleBtn.addEventListener('click', () => {
         setPasswordVisibility(passwordInput.type === 'password');
      });

      passwordInput.parentElement?.addEventListener('mouseleave', () => {
         setPasswordVisibility(false);
      });
   }
}
