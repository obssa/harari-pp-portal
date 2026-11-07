// The membership form (ADR-0076): the day list follows the chosen calendar, month and year, and the Fayda and phone
// fields take only what they accept as the visitor types. The server checks everything again; without this script the
// form still works and the server says what is wrong.
const birth = document.querySelector('[data-birth-date]');

const daysIn = (calendar, year, month) => {
    if (calendar === 'ethiopian') {
        // Thirty days in each of the twelve months; Pagume has five, six in the year before a Gregorian leap year.
        return month === 13 ? (year % 4 === 3 ? 6 : 5) : 30;
    }

    return new Date(Date.UTC(year || 2000, month, 0)).getUTCDate();
};

const limitDays = () => {
    const calendar = birth.querySelector('input[name="birth_calendar"]:checked')?.value ?? 'ethiopian';
    const month = Number(birth.querySelector(`#birth_month_${calendar}`)?.value || 0);
    const year = Number(birth.querySelector('#birth_year')?.value || 0);
    const day = birth.querySelector('#birth_day');
    if (!day || !month) {
        return;
    }
    const days = daysIn(calendar, year, month);
    for (const option of day.options) {
        const number = Number(option.value);
        option.hidden = option.disabled = number > days;
    }
    if (Number(day.value) > days) {
        day.value = '';
    }
};

if (birth) {
    birth.addEventListener('change', limitDays);
    birth.querySelector('#birth_year')?.addEventListener('input', limitDays);
    limitDays();
}

// Digits and spaces only (the Fayda number); digits, spaces and a leading + (the phone).
document.querySelectorAll('[data-digits]').forEach((input) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^\d ]/g, '');
    });
});
document.querySelectorAll('[data-phone]').forEach((input) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^\d +]/g, '').replace(/(?!^)\+/g, '');
    });
});
document.querySelectorAll('#birth_year').forEach((input) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '');
    });
});
