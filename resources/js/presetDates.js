const presetCalculators = {
    thisMonth: (date) => ({
        start: new Date(date.getFullYear(), date.getMonth(), 1),
        end: new Date(date.getFullYear(), date.getMonth() + 1, 0),
    }),
    thisQuarter: (date) => {
        const quarter = Math.floor((date.getMonth() + 3) / 3);
        return {
            start: new Date(date.getFullYear(), (quarter - 1) * 3, 1),
            end: new Date(date.getFullYear(), quarter * 3, 0),
        };
    },
    thisYear: (date) => ({
        start: new Date(date.getFullYear(), 0, 1),
        end: new Date(date.getFullYear(), 12, 0),
    }),
    thisMonthToDate: (date) => ({
        start: new Date(date.getFullYear(), date.getMonth(), 1),
        end: date,
    }),
    thisQuarterToDate: (date) => {
        const quarter = Math.floor((date.getMonth() + 3) / 3);
        return {
            start: new Date(date.getFullYear(), (quarter - 1) * 3, 1),
            end: date,
        };
    },
    thisYearToDate: (date) => ({
        start: new Date(date.getFullYear(), 0, 1),
        end: date,
    }),
    yesterday: (date) => ({
        start: new Date(date.getFullYear(), date.getMonth(), date.getDate() - 1),
        end: new Date(date.getFullYear(), date.getMonth(), date.getDate() - 1),
    }),
    previous7Days: (date) => ({
        start: new Date(date.getFullYear(), date.getMonth(), date.getDate() - 7),
        end: date,
    }),
    previous30Days: (date) => ({
        start: new Date(date.getFullYear(), date.getMonth(), date.getDate() - 30),
        end: date,
    }),
    previous90Days: (date) => ({
        start: new Date(date.getFullYear(), date.getMonth(), date.getDate() - 90),
        end: date,
    }),
    previous180Days: (date) => ({
        start: new Date(date.getFullYear(), date.getMonth(), date.getDate() - 180),
        end: date,
    }),
    previous365Days: (date) => ({
        start: new Date(date.getFullYear(), date.getMonth(), date.getDate() - 365),
        end: date,
    }),
    previousMonth: (date) => ({
        start: new Date(date.getFullYear(), date.getMonth() - 1, 1),
        end: new Date(date.getFullYear(), date.getMonth(), 0),
    }),
    previousMonthToDate: (date) => ({
        start: new Date(date.getFullYear(), date.getMonth() - 1, 1),
        end: date,
    }),
};

export default presetCalculators;