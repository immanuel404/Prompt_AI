import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './components/App.vue';
import Home from './components/Home.vue';

// Define the routes
const routes = [
    { path: '/', component: Home },
];

// Create the router instance
const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Create and mount the Vue application
const app = createApp(App);
app.use(router);
app.mount('#app');
