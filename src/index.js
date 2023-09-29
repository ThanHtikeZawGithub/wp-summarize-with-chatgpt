import App from "./App";
import { render } from '@wordpress/element';

import './style/main.scss';

const container = document.getElementById('user-summary-app');

if (container) {
    render(<App />, container);
}
