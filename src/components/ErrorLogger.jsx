import React, { useEffect } from 'react';

const ErrorLogger = () => {
    useEffect(() => {
        window.javascriptErrors = [];

        const errorHandler = (event) => {
            window.javascriptErrors.push(event.type + ': ' + event.message);
        };

        const rejectionHandler = (event) => {
            window.javascriptErrors.push(event.reason);
        };

        window.addEventListener('error', errorHandler);
        window.addEventListener('unhandledrejection', rejectionHandler);

        const oldConsoleError = console.error;
        console.error = function () {
            window.javascriptErrors.push(arguments);
            oldConsoleError.apply(console, arguments);
        };

        return () => {
            window.removeEventListener('error', errorHandler);
            window.removeEventListener('unhandledrejection', rejectionHandler);
            console.error = oldConsoleError;
        };
    }, []);

    return null;
};

export default ErrorLogger;
