const toRupiah = (number, useCommas = true) => {
    const formatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: number % 1 ? 2 : 0,
    });
    return formatter.format(number).replace(useCommas ? /,/g : /\./g, useCommas ? '.' : ',')
};

