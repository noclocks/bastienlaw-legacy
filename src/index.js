import React from 'react';
import ReactDOM from 'react-dom/client';
import './App.scss'
import App from './App';
import reportWebVitals from './reportWebVitals';
import ErrorLogger from './components/ErrorLogger';
// import WebVitals from './components/WebVitals';

const root = ReactDOM.createRoot(document.getElementById('root'));

root.render(
  <React.StrictMode>
    <ErrorLogger />
    <App />
  </React.StrictMode>
);

// If you want to start measuring performance in your app, pass a function
// to log results (for example: reportWebVitals(console.log))
// or send to an analytics endpoint. Learn more: https://bit.ly/CRA-vitals
reportWebVitals();

const menu = document.getElementById("menu-btn");
const nav = document.getElementById("nav");

menu.addEventListener("click", (e) => {
	if (menu.innerHTML === "Menu") {
		menu.innerHTML = "Close";
	} else {
		menu.innerHTML = "Menu";
	}
	nav.classList.toggle("visible");
});

nav.addEventListener("click", (e) =>{
  menu.innerHTML = "Menu";
  nav.classList.remove("visible");
})
