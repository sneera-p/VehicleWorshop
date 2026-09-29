// Entry point — compiled by Bun into public/assets/index.js.

console.log("vwork index.ts (.js) loaded");

const es = new EventSource('/greeting');
es.addEventListener('dummy', e => console.log(e.data));
