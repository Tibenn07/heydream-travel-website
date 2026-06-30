document.addEventListener('DOMContentLoaded', () => {
    // Reveal animations on scroll
    const revealElements = document.querySelectorAll('.reveal');

    const revealOnScroll = () => {
        revealElements.forEach(el => {
            const elementTop = el.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            if (elementTop < windowHeight - 50) {
                el.classList.add('active');
            }
        });
    };

    // Initial check
    revealOnScroll();
    window.addEventListener('scroll', revealOnScroll);

    // Hero Slider
    const slides = document.querySelectorAll('.slide');
    if (slides.length > 0) {
        let currentSlide = 0;
        setInterval(() => {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }, 5000);
    }

    // Visa Fields Toggle
    const travelTypeSelect = document.getElementById('travel-type');
    const visaFields = document.getElementById('visa-fields');

    if (travelTypeSelect && visaFields) {
        travelTypeSelect.addEventListener('change', (e) => {
            if (e.target.value === 'visa-assistance') {
                visaFields.style.display = 'block';
                setTimeout(() => visaFields.classList.add('active'), 10);
            } else {
                visaFields.classList.remove('active');
                setTimeout(() => visaFields.style.display = 'none', 800);
            }
        });
    }

    // Social Referral Selection
    const socialOptions = document.querySelectorAll('.social-option');
    const referralInput = document.getElementById('referral_source');

    socialOptions.forEach(option => {
        option.addEventListener('click', () => {
            // Remove active class from all
            socialOptions.forEach(opt => opt.classList.remove('active'));
            // Add to clicked
            option.classList.add('active');
            // Update hidden input
            referralInput.value = option.dataset.value;
        });
    });

    const inquiryForm = document.getElementById('travel-inquiry-form');
    const successOverlay = document.getElementById('success-message');

    if (inquiryForm) {
        inquiryForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('submit-btn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';

            const formData = new FormData(inquiryForm);
            const data = Object.fromEntries(formData.entries());

            // Manual validation for social referral since it's a hidden input
            if (!data.referral_source) {
                Swal.fire({
                    icon: 'info',
                    title: 'One more thing...',
                    text: 'Please let us know how you heard about us by selecting one of the social media options.',
                    confirmButtonColor: '#003580'
                });
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                return;
            }


            // Handle multiple checkboxes for interests
            const interests = [];
            inquiryForm.querySelectorAll('input[name="interests[]"]:checked').forEach(cb => {
                interests.push(cb.value);
            });
            data.interests = interests;
            
            // Capture Marketing Consent
            data.marketing_consent = document.getElementById('promo-agree').checked ? 1 : 0;

            try {
                const response = await fetch('process_inquiry.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Inquiry Sent!',
                        text: 'Thank you for choosing HeyDream. Our travel experts will get back to you shortly.',
                        confirmButtonColor: '#003580'
                    });
                    inquiryForm.reset();
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Notice',
                        text: result.message,
                        confirmButtonColor: '#003580'
                    });
                }
            } catch (error) {
                console.error('Error submitting form:', error);
                alert('An error occurred. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }
});

function closeSuccess() {
    document.getElementById('success-message').style.display = 'none';
}
