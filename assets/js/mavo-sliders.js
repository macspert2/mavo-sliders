(function () {
	'use strict';

	function MavoSlider(el) {
		var track    = el.querySelector('.mavo-slider__track');
		var slides   = el.querySelectorAll('.mavo-slider__slide');
		var count    = slides.length;
		var interval = parseInt(el.getAttribute('data-interval'), 10) || 5000;
		var current  = 0;
		var timer    = null;
		var hovered  = false;
		var inView   = false;

		if (!track || count < 2) return;

		function goTo(n) {
			current = ((n % count) + count) % count;
			track.style.willChange = 'transform';
			track.style.transform = 'translateX(-' + (current * 100) + '%)';
			track.addEventListener('transitionend', function cleanup() {
				track.style.willChange = 'auto';
				track.removeEventListener('transitionend', cleanup);
			});
		}

		function start() {
			if (!timer) {
				timer = setInterval(function () { goTo(current + 1); }, interval);
			}
		}

		function stop() {
			clearInterval(timer);
			timer = null;
		}

		function sync() {
			if (!hovered && inView && document.visibilityState !== 'hidden') {
				start();
			} else {
				stop();
			}
		}

		el.addEventListener('mouseenter', function () { hovered = true;  sync(); });
		el.addEventListener('mouseleave', function () { hovered = false; sync(); });

		document.addEventListener('visibilitychange', sync);

		new IntersectionObserver(function (entries) {
			inView = entries[0].isIntersecting;
			sync();
		}, { threshold: 0 }).observe(el);
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.mavo-slider').forEach(MavoSlider);
	});
}());
