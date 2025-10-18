import { useEffect, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';

const PaymentStatusChecker = ({ transactionId, orderId, redirectAfterPayment = true }) => {
    const [status, setStatus] = useState('checking');
    const [checkCount, setCheckCount] = useState(0);
    const maxChecks = 10; // Maximum number of status checks
    
    useEffect(() => {
        const checkPaymentStatus = async () => {
            try {
                const response = await axios.get(`/midtrans/status/${orderId}`);
                
                
                
                if (response.data.success && 
                   (response.data.transaction.payment_status === 'paid' || 
                    response.data.transaction.payment_status === 'settlement' ||
                    response.data.transaction.payment_status === 'capture' ||
                    (response.data.midtrans_status && 
                     (response.data.midtrans_status.transaction_status === 'settlement' || 
                      response.data.midtrans_status.transaction_status === 'capture')))) {
                    
                    setStatus('success');
                    
                    // If redirectAfterPayment is true, redirect to thank you page
                    if (redirectAfterPayment) {
                        
                        router.visit(`/thankyou/${transactionId}`);
                    }
                    
                    return; // Stop checking after successful payment
                } else if (checkCount >= maxChecks) {
                    setStatus('timeout');
                } else {
                    // Payment still pending, continue checking
                    setStatus('pending');
                    setCheckCount(prevCount => prevCount + 1);
                    
                    // Schedule next check after 3 seconds
                    setTimeout(checkPaymentStatus, 3000);
                }
            } catch (error) {
                
                setStatus('error');
                
                if (checkCount < maxChecks) {
                    setCheckCount(prevCount => prevCount + 1);
                    setTimeout(checkPaymentStatus, 3000);
                } else {
                    setStatus('timeout');
                }
            }
        };
        
        // Start checking status
        checkPaymentStatus();
        
        return () => {
            // Cleanup function to cancel any pending checks if component unmounts
        };
    }, [orderId, transactionId, redirectAfterPayment]);
    
    return {
        status,
        isChecking: status === 'checking' || status === 'pending',
        isSuccess: status === 'success',
        isError: status === 'error',
        isTimeout: status === 'timeout'
    };
};

export default PaymentStatusChecker;