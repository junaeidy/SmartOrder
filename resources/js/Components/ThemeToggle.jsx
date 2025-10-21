import React from 'react';
import { Sun, Moon } from 'lucide-react';
import { useTheme } from './ThemeProvider';

const ThemeToggle = ({ className = '' }) => {
  const { theme, toggleTheme } = useTheme();
  const isDark = theme === 'dark';

  return (
    <button
      onClick={toggleTheme}
      aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
      className={`fixed bottom-5 right-5 z-50 rounded-full p-3 shadow-lg border transition-colors
        bg-white text-gray-700 hover:bg-gray-50 border-gray-200
        dark:bg-gray-800 dark:text-yellow-300 dark:hover:bg-gray-700 dark:border-gray-700 ${className}`}
    >
      {isDark ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
    </button>
  );
};

export default ThemeToggle;
