import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['X-Locale'] =
    window.localStorage.getItem('hr_ai_locale') === 'kk' ? 'kk' : 'ru';

const authToken = window.localStorage.getItem('token');
if (authToken) {
    axios.defaults.headers.common.Authorization = `Bearer ${authToken}`;
}

const token = document.querySelector('meta[name="csrf-token"]');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
} else {
    console.error('CSRF token not found in <meta> tag.');
}
