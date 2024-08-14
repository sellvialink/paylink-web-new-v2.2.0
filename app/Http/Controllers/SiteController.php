<?php

namespace App\Http\Controllers;


use Exception;
use App\Models\Contact;
use App\Models\Forexcrow;
use App\Models\FaqSection;
use App\Models\Subscriber;
use App\Models\Admin\Event;
use Illuminate\Support\Str;
use App\Models\CategoryType;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Http\Helpers\Response;
use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Models\Admin\SetupPage;
use App\Models\Admin\WebJornal;
use App\Models\User\PaymentLink;
use App\Models\Admin\SiteSections;
use App\Constants\SiteSectionConst;
use Illuminate\Support\Facades\Session;
use App\Models\Admin\TransactionSetting;
use Illuminate\Support\Facades\Validator;
use App\Providers\Admin\BasicSettingsProvider;
use App\Http\Helpers\PaymentGateway as PaymentGatewayHelper;

class SiteController extends Controller
{
    public function home(){
        $basic_settings         = BasicSettingsProvider::get();
        $page_title             = $basic_settings->site_title ?? "Home";
        $section_slug           = Str::slug(SiteSectionConst::HOME_BANNER);
        $home_banner            = SiteSections::getData($section_slug)->first();

        $security_slug          = Str::slug(SiteSectionConst::SECURITY_SECTION);
        $security_section       = SiteSections::getData($security_slug)->first();

        $how_works_section_slug = Str::slug(SiteSectionConst::HOW_IT_WORK);
        $how_works_section      = SiteSections::getData($how_works_section_slug)->first();

        $download_slug          = Str::slug(SiteSectionConst::DOWNLOAD_SECTION);
        $download               = SiteSections::getData($download_slug)->first();

        $why_chose_us_slug      = Str::slug(SiteSectionConst::WHY_CHOSE_US_SECTION);
        $why_chose_us           = SiteSections::getData($why_chose_us_slug)->first();
        $testimonial_slug       = Str::slug(SiteSectionConst::TESTIMONIAL_SECTION);
        $testimonial            = SiteSections::getData($testimonial_slug)->first();
        $partner_slug           = Str::slug(SiteSectionConst::TOP_PARTNER);
        $partner                = SiteSections::getData($partner_slug)->first();

        return view('frontend.index',compact(
            'page_title',
            'home_banner',
            'security_section',
            'download',
            'why_chose_us',
            'testimonial',
            'partner',
            'how_works_section',
        ));

    }
    public function services(){
        $page_title = "Services";
        $service_slug = Str::slug(SiteSectionConst::SERVICE_SECTION);
        $service = SiteSections::getData($service_slug)->first();
        return view('frontend.pages.services',compact('page_title','service'));
    }
    public function aboutUs(){
        $page_title = "About Us";
        $section_slug = Str::slug(SiteSectionConst::ABOUT_SECTION);
        $about = SiteSections::getData($section_slug)->first();

        $faq_slug = Str::slug(SiteSectionConst::FAQ_SECTION);
        $faq = SiteSections::getData($faq_slug)->first();

        return view('frontend.pages.about-us',compact('page_title','about','faq'));
    }

    public function faqs(){
        $page_title = "Faq";
        $faq_slug = Str::slug(SiteSectionConst::FAQ_SECTION);
        $faq = SiteSections::getData($faq_slug)->first();
        return view('frontend.faqs',compact('page_title','faq'));
    }

    public function webJournal(){
        $page_title = "Web Journal";
        $recent_journals = WebJornal::with('category')->where('status', 1)->orderBy('id', 'desc')->limit(3)->get();
        $journals = WebJornal::with('category')->where('status', 1)->paginate(6);
        $categories = CategoryType::with('webJournals')->where('type', 1)->where('status', 1)->orderBy('id','desc')->get();
        return view('frontend.pages.web-journal',compact(
            'page_title',
            'journals',
            'recent_journals',
            'categories',
        ));
    }
    public function webJournalDetails($id,$slug){
        $page_title = "Web Journals Details";
        $recent_journals = WebJornal::with('category')->where('status', 1)->orderBy('id', 'desc')->limit(3)->get();
        $journal = WebJornal::with('category')->findOrFail($id);
        $journals = WebJornal::with('category')->where('status', 1)->paginate(6);
        $categories = CategoryType::with('webJournals')->where('type', 1)->where('status', 1)->orderBy('id','desc')->get();
        return view('frontend.pages.web-journal-details',compact(
            'page_title',
            'journal',
            'recent_journals',
            'journals',
            'categories',
        ));
    }
    public function contactUs(){
        $page_title = "Contact";
        $section_slug = Str::slug(SiteSectionConst::CONTACT_SECTION);
        $contact_us = SiteSections::getData($section_slug)->first();

        return view('frontend.pages.contact-us',compact('page_title','contact_us'));
    }

    public function pageView($slug){
        $defualt = get_default_language_code()??'en';
        $page = SetupPage::where('slug', $slug)->where('status', 1)->first();
        if(empty($page)){
            abort(404);
        }
        $page_title = $page->title->language->$defualt->title;

        return view('frontend.page',compact('page_title', 'page'));
    }

    /**
     * This method for store subscriber
     * @method POST
     * @return Illuminate\Http\Request Response
     * @param Illuminate\Http\Request $request
     */
    public function subscriber(Request $request){
        if($request->ajax()){
            $validator = Validator::make($request->all(),[
                'email' => 'email|unique:subscribers,email'
            ]);

            if($validator->stopOnFirstFailure()->fails()){
                $error = ['errors' => $validator->errors()];
                return Response::error($error, null, 404);
            }

            $validated = $validator->safe()->all();

            try{
                Subscriber::create($validated);
            }catch(Exception $e) {
                $error = ['error' => [__('Something went wrong! Please try again.')]];
                return Response::error($error,null,500);
            }

            $success = ['success' => [__('Your email added to our newsletter').'!']];
            return Response::success($success,null,200);
        }
    }

    /**
     * This method for store subscriber
     * @method POST
     * @return Illuminate\Http\Request Response
     * @param Illuminate\Http\Request $request
     */
    public function contactStore(Request $request){
        if($request->ajax()){
            $validator = Validator::make($request->all(), [
                'name'    => 'required|string',
                'email'   => 'required|email',
                'message' => 'required|string',
            ]);

            if($validator->stopOnFirstFailure()->fails()){
                $error = ['errors' => $validator->errors()];
                return Response::error($error, null, 500);
            }

            $validated = $validator->safe()->all();

            try {
                Contact::create($validated);
            } catch (\Exception $th) {
                $error = ['error' => [__('Something went wrong!. Please try again.')]];
                return Response::error($error, null, 500);
            }

            $success = ['success' => [__('Your message submitted').'!']];
            return Response::success($success,null,200);
        }
    }

    public function languageSwitch(Request $request) {
        $code = $request->target;
        $language = Language::where("code",$code);
        if(!$language->exists()) {
            return back()->with(['error' => [__('Oops! Language not found').'!']]);
        }

        Session::put('local',$code);

        return back()->with(['success' => [__('Language switch successfully').'!']]);
    }


    /**
     * Redirect Users for collecting payment via Button Pay (JS Checkout)
     */
    public function redirectBtnPay(Request $request, $gateway)
    {
        $request_data = $request->all();
        $temp_token = $request_data['token'];

        $temp_data = TemporaryData::where('identifier', $temp_token)->first();
        if(!$temp_data) throw new Exception(__('Requested with invalid token'));
        try{

            return PaymentGatewayHelper::init([])->type($temp_data->data->type)->handleBtnPay($gateway, $request->all());
        }catch(Exception $e) {
            return redirect()->route('index')->with(['error' => [$e->getMessage()]]);
        }
    }

    public function callback(Request $request,$gateway) {
        $callback_token = $request->get('token');
        $callback_data = $request->all();
        $tempData = TemporaryData::where('identifier',$callback_data['payload']['order']['entity']['receipt'])->first();
        try{
            PaymentGatewayHelper::init([])->type($tempData['data']->type)->handleCallback($callback_token,$callback_data,$gateway);
        }catch(Exception $e) {
            // handle Error
            logger($e);
        }
    }
}
