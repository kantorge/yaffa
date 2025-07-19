import { detectDateFormat } from '../qifParser';

describe('detectDateFormat', () => {
  it('returns empty_or_invalid_input for empty array', () => {
    expect(detectDateFormat([])).toEqual({ format: null, error: 'empty_or_invalid_input' });
  });

  it('returns empty_or_invalid_input for non-array input', () => {
    expect(detectDateFormat(null)).toEqual({ format: null, error: 'empty_or_invalid_input' });
    expect(detectDateFormat(undefined)).toEqual({ format: null, error: 'empty_or_invalid_input' });
    expect(detectDateFormat('2024-06-01')).toEqual({ format: null, error: 'empty_or_invalid_input' });
  });

  it('detects MM/DD/YYYY format', () => {
    const dates = ['12/31/2023', '01/15/2024', '11/05/2022'];
    expect(detectDateFormat(dates)).toEqual({ format: 'MM/DD/YYYY', error: null });
  });

  it('detects DD-MM-YYYY format', () => {
    const dates = ['31-12-2023', '15-01-2024', '05-11-2022'];
    expect(detectDateFormat(dates)).toEqual({ format: 'DD-MM-YYYY', error: null });
  });

  it('detects YYYY.MM.DD format', () => {
    const dates = ['2023.12.31', '2024.01.15', '2022.11.05'];
    expect(detectDateFormat(dates)).toEqual({ format: 'YYYY.MM.DD', error: null });
  });

  it('detects YYYY\'MM\'DD format', () => {
    const dates = ["2023'12'31", "2024'01'15", "2022'11'05"];
    expect(detectDateFormat(dates)).toEqual({ format: "YYYY'MM'DD", error: null });
  });

  it('returns ambiguous_or_no_match for ambiguous dates', () => {
    // 01/02/2023 could be MM/DD/YYYY or DD/MM/YYYY
    const dates = ['01/02/2023', '03/04/2023'];
    expect(detectDateFormat(dates)).toEqual({ format: null, error: 'ambiguous_or_no_match' });
  });

  it('returns multiple_conflicts for conflicting formats', () => {
    // Mix of MM/DD/YYYY and DD-MM-YYYY
    const dates = ['12/31/2023', '31-12-2023'];
    expect(detectDateFormat(dates)).toEqual({ format: null, error: 'multiple_conflicts' });
  });

  it('ignores invalid dates and still detects format if possible', () => {
    const dates = ['12/31/2023', 'notadate', '01/15/2024'];
    expect(detectDateFormat(dates)).toEqual({ format: 'MM/DD/YYYY', error: null });
  });

  it('returns ambiguous_or_no_match if all dates are invalid', () => {
    const dates = ['notadate', 'anotherbad', '31/31/2023'];
    expect(detectDateFormat(dates)).toEqual({ format: null, error: 'ambiguous_or_no_match' });
  });

  it('detects YYYY-MM-DD format', () => {
    const dates = ['2024-06-01', '2023-12-31', '2022-01-05'];
    expect(detectDateFormat(dates)).toEqual({ format: 'YYYY-MM-DD', error: null });
  });

  it('detects MM.DD.YYYY format', () => {
    const dates = ['12.31.2023', '01.15.2024', '11.05.2022'];
    expect(detectDateFormat(dates)).toEqual({ format: 'MM.DD.YYYY', error: null });
  });

  it('detects DD/MM/YYYY format', () => {
    const dates = ['31/12/2023', '15/01/2024', '05/11/2022'];
    expect(detectDateFormat(dates)).toEqual({ format: 'DD/MM/YYYY', error: null });
  });

  it('detects YYYY/DD/MM format', () => {
    const dates = ['2023-31-12', '2024-15-01', '2022-05-11'];
    expect(detectDateFormat(dates)).toEqual({ format: 'YYYY-DD-MM', error: null });
  });
});
