import { helper } from '@ember/component/helper';

export default helper(function abbreviateInterval([interval]) {
    if (interval.startsWith('ye')) {
        return 'yr';
    } else if (interval.startsWith('mo')) {
        return 'mo';
    }
});
