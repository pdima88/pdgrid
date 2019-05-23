function pdgridFormatDateRange(startDate, endDate) {
    var m1 = moment(startDate);
    var m2 = moment(endDate);
    if (m1.year() == m2.year()) {
        if (m1.month() == m2.month()) {
            if (m1.date() == m2.date()) {
                return m1.format('D MMM YYYY');
            } else {
                return m1.format('D')+' - '+m2.format('D MMM YYYY');
            }
        } else {
            return m1.format('D MMM')+' - '+m2.format('D MMM YYYY');
        }
    } else {
        return m1.format('D MMM YYYY')+' - '+m2.format('D MMM YYYY');
    }
}