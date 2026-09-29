// Entry point — compiled by Bun into public/assets/index.js.

import { createSSEClient } from "@flamefrontend/sse-runtime-core";

console.log("vwork .ts (.js) loaded");

type Events = {
   dummy: {
      message: string;
   }
}

const client = createSSEClient<Events>({
   key: ['greeting'],
   url: '/greeting',
   events: {
      dummy: (m) => {
         if (window.location.pathname === '/dummy') {
            var element = document.getElementById('msg')
            if (element !== null) {
               element.textContent = m.message;
            }
         }
      }
   },
   coordination: {
      enabled: true,
      mode: 'single-tab'
   }
});

client.connect();
