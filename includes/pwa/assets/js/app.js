// app.js
import React from 'react';
import { createRoot } from 'react-dom/client';

const App = () => (
    <div style={{padding: '20px'}}>
        <h1>DrivingApp Test</h1>
        <p>La PWA está funcionando correctamente</p>
    </div>
);

ReactDOM.createRoot(document.getElementById('root')).render(<App />);