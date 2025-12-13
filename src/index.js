import { createRoot } from '@wordpress/element';
import App from './App';
import './styles.css';

const container = document.getElementById('wwa-react-root');

if (container) {
    createRoot(container).render(<App />);
}
