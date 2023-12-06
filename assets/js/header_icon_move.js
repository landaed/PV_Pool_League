window.onscroll = function() {
    var logo = document.getElementById('scrolling-header-logo');
    var header = document.getElementById('header');
    var scroll = window.pageYOffset || document.documentElement.scrollTop;

    // Debug statement
    console.log(scroll);

    // Logo animation
    if (scroll > 100) {
    logo.classList.add('scrolled');
    // Additional JavaScript to animate the logo to button
    logo.style.position = 'fixed';
    logo.style.top = '';
    logo.style.left = '';
    logo.style.bottom = '80px'; // Adjust to align with hamburger menu
    logo.style.right = '20px'; // Adjust as necessary
    // Transform logo to button look
    logo.style.backgroundImage = "url('path_to_button_image')";
    logo.style.width = '50px'; // Button size
    logo.style.height = '50px'; // Button size
} else {
    logo.classList.remove('scrolled');
    // Reset styles
    logo.style.position = 'absolute'; // Or as per initial state
    logo.style.bottom = '';
    logo.style.right = '';
    logo.style.left = '5%';

    logo.style.top = '10px';
    logo.style.backgroundImage = '';
    logo.style.width = '100px'; // Original logo size
    logo.style.height = ''; // Original logo size
}

    // Header opacity
    var opacity = 1; // You may want to change this based on the scroll value
    header.style.opacity = opacity;
};
