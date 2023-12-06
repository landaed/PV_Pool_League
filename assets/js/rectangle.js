function toggleExpandable(event) {
    event.stopPropagation(); // add this line to stop event propagation
    const expandableContent = event.target.querySelector('ul');
    expandableContent.classList.toggle('hidden');
    event.target.classList.toggle('expanded');
}

function checkDevice(event) {
      // This is a simple way to detect a mobile device by checking for a few common devices.
      // Note: This may not catch all mobile devices, as the userAgent string can vary widely.
      if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
          // Mobile device detected - do nothing
      } else {
          // Not a mobile device - call the function
          toggleRectangle(event);
      }
  }

function toggleRectangle(event) {
    let rectangle;

    // If the click event happened on an icon inside the rectangle, find the rectangle
    if (event.target.parentElement.classList.contains('rectangle')) {
        rectangle = event.target.parentElement;
    }

    // Else if the click event happened directly on the rectangle, use it directly
    else if (event.target.classList.contains('rectangle')) {
        rectangle = event.target;
    }

    // Toggle the rectangle's expanded state only if we found a rectangle
    if (rectangle) {
        rectangle.classList.toggle('rectangle-expanded');
        rectangle.parentElement.parentElement.firstElementChild.firstElementChild.firstElementChild.scrollIntoView(true);
    }
}
