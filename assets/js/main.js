(function() {
  "use strict";

  /**
   * Easy selector helper function
   */
  const select = (el, all = false) => {
    el = el.trim()
    if (all) {
      return [...document.querySelectorAll(el)]
    } else {
      return document.querySelector(el)
    }
  }

  /**
   * Easy event listener function
   */
  const on = (type, el, listener, all = false) => {
    let selectEl = select(el, all)
    if (selectEl) {
      if (all) {
        selectEl.forEach(e => e.addEventListener(type, listener))
      } else {
        selectEl.addEventListener(type, listener)
      }
    }
  }

  /**
  * Easy on scroll event listener
   */
  const onscroll = (el, listener) => {
    el.addEventListener('scroll', listener)
  }

  /**
   * Navbar links active state on scroll
   */
  let navbarlinks = select('#navbar .scrollto', true)
  const navbarlinksActive = () => {
    let position = window.scrollY + 200
    navbarlinks.forEach(navbarlink => {
      if (!navbarlink.hash) return
      let section = select(navbarlink.hash)
      if (!section) return
      if (position >= section.offsetTop && position <= (section.offsetTop + section.offsetHeight)) {
        navbarlink.classList.add('active')
      } else {
        navbarlink.classList.remove('active')
      }
    })
  }
  window.addEventListener('load', navbarlinksActive)
  onscroll(document, navbarlinksActive)

  /**
   * Scrolls to an element with header offset
   */
  const scrollto = (el) => {
    let header = select('#header')
    let offset = header.offsetHeight

    let elementPos = select(el).offsetTop
    window.scrollTo({
      top: elementPos - offset,
      behavior: 'smooth'
    })
  }

  /**
   * Toggle .header-scrolled class to #header when page is scrolled
   */
  let selectHeader = select('#header')
  if (selectHeader) {
    const headerScrolled = () => {
      if (window.scrollY > 100) {
        selectHeader.classList.add('header-scrolled')
      } else {
        selectHeader.classList.remove('header-scrolled')
      }
    }
    window.addEventListener('load', headerScrolled)
    onscroll(document, headerScrolled)
  }

  /**
   * Back to top button
   */
  let backtotop = select('.back-to-top')
  if (backtotop) {
    const toggleBacktotop = () => {
      if (window.scrollY > 100) {
        backtotop.classList.add('active')
      } else {
        backtotop.classList.remove('active')
      }
    }
    window.addEventListener('load', toggleBacktotop)
    onscroll(document, toggleBacktotop)
  }

  /**
   * Mobile nav toggle
   */
  on('click', '.mobile-nav-toggle', function(e) {
    select('#navbar').classList.toggle('navbar-mobile')
    this.classList.toggle('bi-list')
    this.classList.toggle('bi-x')
  })

  /**
   * Scrool with ofset on links with a class name .scrollto
   */
  on('click', '.scrollto', function(e) {
    if (select(this.hash)) {
      e.preventDefault()

      let navbar = select('#navbar')
      if (navbar.classList.contains('navbar-mobile')) {
        navbar.classList.remove('navbar-mobile')
        let navbarToggle = select('.mobile-nav-toggle')
        navbarToggle.classList.toggle('bi-list')
        navbarToggle.classList.toggle('bi-x')
      }
      scrollto(this.hash)
    }
  }, true)

  /**
   * Scroll with ofset on page load with hash links in the url
   */
  window.addEventListener('load', () => {
    if (window.location.hash) {
      if (select(window.location.hash)) {
        scrollto(window.location.hash)
      }
    }
  });

  /**
   * Preloader
   */
  let preloader = select('#preloader');
  if (preloader) {
    window.addEventListener('load', () => {
      preloader.remove()
    });
  }

  const portfolioSlider = select('.portfolio-slider');
  if (portfolioSlider) {
    new Swiper(portfolioSlider, {
      slidesPerView: 1,
      spaceBetween: 24,
      rewind: true,
      speed: 600,
      grabCursor: true,
      keyboard: {
        enabled: true,
        onlyInViewport: true
      },
      navigation: {
        nextEl: select('.portfolio-next'),
        prevEl: select('.portfolio-prev')
      },
      pagination: {
        el: select('.portfolio-pagination'),
        clickable: true
      }
    });
  }

  const serviceSlider = select('.service-slider');
  if (serviceSlider) {
    new Swiper(serviceSlider, {
      slidesPerView: 1,
      spaceBetween: 24,
      rewind: true,
      speed: 600,
      grabCursor: true,
      keyboard: {
        enabled: true,
        onlyInViewport: true
      },
      navigation: {
        nextEl: select('.service-next'),
        prevEl: select('.service-prev')
      },
      pagination: {
        el: select('.service-pagination'),
        clickable: true
      }
    });
  }

  const contactForm = select('#contact-form');
  if (contactForm) {
    const loadingMessage = contactForm.querySelector('.loading');
    const errorMessage = contactForm.querySelector('.error-message');
    const sentMessage = contactForm.querySelector('.sent-message');
    const submitButton = contactForm.querySelector('button[type="submit"]');

    contactForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!contactForm.reportValidity()) return;

      const formData = new FormData(contactForm);
      const payload = Object.fromEntries(formData.entries());

      loadingMessage.classList.add('d-block');
      errorMessage.classList.remove('d-block');
      errorMessage.textContent = '';
      sentMessage.classList.remove('d-block');
      submitButton.disabled = true;

      try {
        const response = await fetch(contactForm.action, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Your message could not be sent.');

        contactForm.reset();
        sentMessage.textContent = 'Your message has been sent. Thank you!';
        sentMessage.classList.add('d-block');
      } catch (error) {
        errorMessage.textContent = error.message || 'Your message could not be sent. Please try again later.';
        errorMessage.classList.add('d-block');
      } finally {
        loadingMessage.classList.remove('d-block');
        submitButton.disabled = false;
      }
    });
  }

  const reviewForm = select('#review-form');
  const reviewList = select('#review-list');
  const reviewSlider = select('.review-swiper');
  if (reviewForm && reviewList && reviewSlider) {
    const reviewStatus = select('#review-form-status');
    const reviewApi = 'assets/php/reviews.php';
    const reviewPrevious = select('.review-prev');
    const reviewNext = select('.review-next');
    const reviewPagination = select('.review-pagination');
    let reviewSwiper;

    const updateReviewSlider = (slideCount) => {
      if (!reviewSwiper) {
        reviewSwiper = new Swiper(reviewSlider, {
          direction: 'vertical',
          slidesPerView: 1,
          spaceBetween: 24,
          rewind: true,
          speed: 600,
          autoHeight: true,
          grabCursor: true,
          keyboard: {
            enabled: true,
            onlyInViewport: true
          },
          navigation: {
            nextEl: reviewNext,
            prevEl: reviewPrevious
          },
          pagination: {
            el: reviewPagination,
            clickable: true
          }
        });
      } else {
        reviewSwiper.update();
        reviewSwiper.slideTo(0, 0);
      }

      const hideControls = slideCount <= 1;
      [reviewPrevious, reviewNext, reviewPagination].forEach((control) => {
        control.classList.toggle('review-control-hidden', hideControls);
      });
    };

    const renderReviews = (reviews) => {
      reviewList.replaceChildren();

      if (!reviews.length) {
        const emptyMessage = document.createElement('div');
        emptyMessage.className = 'swiper-slide reviews-empty';
        emptyMessage.textContent = 'No reviews yet. Be the first to leave one.';
        reviewList.appendChild(emptyMessage);
        updateReviewSlider(1);
        return;
      }

      reviews.forEach((review) => {
        const entry = document.createElement('article');
        entry.className = 'review-entry swiper-slide';

        const header = document.createElement('div');
        header.className = 'review-entry-header';

        const author = document.createElement('h3');
        author.className = 'review-author';
        author.textContent = review.name || 'Anonymous';
        header.appendChild(author);

        const date = new Date(review.createdAt);
        if (!Number.isNaN(date.getTime())) {
          const dateLabel = document.createElement('time');
          dateLabel.className = 'review-date';
          dateLabel.dateTime = date.toISOString();
          dateLabel.textContent = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date);
          header.appendChild(dateLabel);
        }

        const rating = Number(review.rating);
        const stars = document.createElement('p');
        stars.className = 'review-stars';
        stars.setAttribute('aria-label', `${rating} out of 5 stars`);
        stars.textContent = `${'★'.repeat(rating)}${'☆'.repeat(5 - rating)}`;

        const text = document.createElement('p');
        text.className = 'review-text';
        text.textContent = review.review;

        entry.append(header, stars, text);
        reviewList.appendChild(entry);
      });

      updateReviewSlider(reviews.length);
    };

    const loadReviews = async () => {
      try {
        const response = await fetch(reviewApi, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Reviews could not be loaded.');
        renderReviews(data.reviews);
        } catch {
        reviewList.replaceChildren();
        const errorMessage = document.createElement('div');
        errorMessage.className = 'swiper-slide reviews-empty';
        errorMessage.textContent = 'Reviews are unavailable right now.';
        reviewList.appendChild(errorMessage);
        updateReviewSlider(1);
      }
    };

    reviewForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!reviewForm.reportValidity()) return;

      const submitButton = reviewForm.querySelector('button[type="submit"]');
      const rating = reviewForm.querySelector('input[name="rating"]:checked');
      const formData = new FormData(reviewForm);
      const payload = {
        name: formData.get('name').trim(),
        rating: Number(rating.value),
        review: formData.get('review').trim(),
        website: formData.get('website')
      };

      submitButton.disabled = true;
      reviewStatus.textContent = 'Posting your review...';

      try {
        const response = await fetch(reviewApi, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Your review could not be posted.');

        renderReviews(data.reviews);
        reviewForm.reset();
        reviewStatus.textContent = 'Thank you. Your review has been posted.';
      } catch (error) {
        reviewStatus.textContent = error.message || 'Your review could not be posted. Please try again.';
      } finally {
        submitButton.disabled = false;
      }
    });

    loadReviews();
  }

  /**
  * Initiate portfolio lightbox
   */
  GLightbox({
    selector: '.portfolio-lightbox'
  });

  /**
   * Animation on scroll
   */
  window.addEventListener('load', () => {
    AOS.init({
      duration: 1000,
      easing: "ease-in-out",
      once: true,
      mirror: false
    });
  });

})()