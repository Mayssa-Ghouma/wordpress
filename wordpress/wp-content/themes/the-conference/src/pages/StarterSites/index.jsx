import { __ } from "@wordpress/i18n";
import { Card } from "../../components";
import { mainDemo, demo2, demo3 } from "../../components/images"

const StarterSites = () => {
    const starterCardSettings = [
        {
            heading: __('The Conference Pro', 'the-conference'),
            imageurl: mainDemo,
            buttonUrl: "https://rarathemesdemo.com/the-conference-pro/"
        },
        {
            heading: __('The Conference Pro​ 2', 'the-conference'),
            imageurl: demo2,
            buttonUrl: "https://rarathemesdemo.com/the-conference-pro-2/"
        },
        {
            heading: __('The Conference Pro​ 3', 'the-conference'),
            imageurl: demo3,
            buttonUrl: "https://rarathemesdemo.com/the-conference-pro-3/"
        }
    ];

    return (
        <>
            <Card
                cardList={starterCardSettings}
                cardPlace='starter'
                cardCol='three-col'
            />
        </>
    )
}

export default StarterSites;