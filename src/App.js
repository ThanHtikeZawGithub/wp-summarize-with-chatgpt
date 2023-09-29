import React from 'react';
import {
  BrowserRouter as Router,
  Route,
  Routes, // Import the new Routes component
} from 'react-router-dom';
import Dashboard from './components/Dashboard';

const App = () => {
  return (
    <Router>
      <Routes>
        <Route path="/user-summary" element={<Dashboard/>} />
      </Routes>
    </Router>
  );
}

export default App;
