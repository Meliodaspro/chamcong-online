import 'package:flutter/material.dart';
const brand=Color(0xFF2563EB);
const textPrimary=Color(0xFF152744);
const textMuted=Color(0xFF6E7D94);
ThemeData appTheme()=>ThemeData(
 useMaterial3:true, brightness:Brightness.light,
 colorScheme:ColorScheme.fromSeed(seedColor:brand,brightness:Brightness.light),
 scaffoldBackgroundColor:const Color(0xFFF3F6FC),
 appBarTheme:const AppBarTheme(backgroundColor:Color(0xFFF3F6FC),foregroundColor:textPrimary,centerTitle:false,scrolledUnderElevation:0,titleTextStyle:TextStyle(fontSize:21,fontWeight:FontWeight.w800,color:textPrimary)),
 cardTheme:CardThemeData(color:Colors.white,elevation:0,margin:const EdgeInsets.symmetric(vertical:6),shape:RoundedRectangleBorder(borderRadius:BorderRadius.circular(20),side:const BorderSide(color:Color(0xFFE6ECF5)))),
 textTheme:const TextTheme(bodyMedium:TextStyle(color:textPrimary,fontSize:14,height:1.45),bodyLarge:TextStyle(color:textPrimary,fontSize:16,height:1.4),titleMedium:TextStyle(color:textPrimary,fontSize:16,fontWeight:FontWeight.w700),titleLarge:TextStyle(color:textPrimary,fontSize:21,fontWeight:FontWeight.w800),headlineSmall:TextStyle(color:textPrimary,fontSize:25,fontWeight:FontWeight.w800)),
 inputDecorationTheme:InputDecorationTheme(filled:true,fillColor:Colors.white,contentPadding:const EdgeInsets.symmetric(horizontal:16,vertical:16),border:OutlineInputBorder(borderRadius:BorderRadius.circular(14),borderSide:const BorderSide(color:Color(0xFFDCE4F0))),enabledBorder:OutlineInputBorder(borderRadius:BorderRadius.circular(14),borderSide:const BorderSide(color:Color(0xFFDCE4F0)))),
 filledButtonTheme:FilledButtonThemeData(style:FilledButton.styleFrom(backgroundColor:brand,foregroundColor:Colors.white,padding:const EdgeInsets.symmetric(horizontal:20,vertical:15),shape:RoundedRectangleBorder(borderRadius:BorderRadius.circular(14)))),
 navigationBarTheme:NavigationBarThemeData(backgroundColor:Colors.white,indicatorColor:const Color(0xFFE7EFFF),height:76,labelTextStyle:WidgetStateProperty.all(const TextStyle(fontSize:11,fontWeight:FontWeight.w700))),
 dividerColor:const Color(0xFFE6ECF5),
);
class DashboardBanner extends StatelessWidget{
 const DashboardBanner({required this.title,required this.subtitle,super.key});
 final String title,subtitle;
 @override Widget build(BuildContext context)=>Container(padding:const EdgeInsets.all(22),decoration:BoxDecoration(gradient:const LinearGradient(colors:[Color(0xFF174DCC),Color(0xFF4387FB)],begin:Alignment.topLeft,end:Alignment.bottomRight),borderRadius:BorderRadius.circular(24)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
 const Row(children:[Icon(Icons.blur_on_rounded,color:Colors.white,size:24),SizedBox(width:8),Text('CHẤM CÔNG',style:TextStyle(color:Colors.white70,fontSize:12,letterSpacing:2,fontWeight:FontWeight.w700))]),
 const SizedBox(height:20),Text(title,style:const TextStyle(color:Colors.white,fontWeight:FontWeight.w800,fontSize:25,height:1.2)),const SizedBox(height:8),Text(subtitle,style:const TextStyle(color:Color(0xFFDDE9FF),height:1.5)),
 ]));
}
class MetricGrid extends StatelessWidget{
 const MetricGrid({required this.items,super.key});
 final List<({String label,String value,IconData icon,Color color})> items;
 @override Widget build(BuildContext context)=>LayoutBuilder(builder:(context,c){
  final columns=c.maxWidth>650?4:2;
  final width=(c.maxWidth-(columns-1)*12)/columns;
  return Wrap(spacing:12,runSpacing:12,children:items.map((m)=>SizedBox(width:width,child:Container(padding:const EdgeInsets.all(16),decoration:BoxDecoration(color:Colors.white,borderRadius:BorderRadius.circular(20),border:Border.all(color:const Color(0xFFE6ECF5))),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Container(padding:const EdgeInsets.all(9),decoration:BoxDecoration(color:m.color.withValues(alpha:0.10),borderRadius:BorderRadius.circular(12)),child:Icon(m.icon,color:m.color,size:22)),const SizedBox(height:14),Text(m.value,style:const TextStyle(fontSize:24,fontWeight:FontWeight.w800,color:textPrimary)),const SizedBox(height:4),Text(m.label,style:const TextStyle(color:textMuted,fontSize:12,fontWeight:FontWeight.w600))])))).toList());
 });
}
class SectionTitle extends StatelessWidget{
 const SectionTitle(this.title,{this.action,super.key}); final String title; final Widget? action;
 @override Widget build(BuildContext context)=>Padding(padding:const EdgeInsets.only(top:24,bottom:10),child:Row(children:[Expanded(child:Text(title,style:const TextStyle(fontSize:17,fontWeight:FontWeight.w800))),if(action!=null)action!]));
}
class StatusPill extends StatelessWidget{
 const StatusPill(this.label,{this.color=brand,super.key});final String label;final Color color;
 @override Widget build(BuildContext context)=>Container(padding:const EdgeInsets.symmetric(horizontal:10,vertical:5),decoration:BoxDecoration(color:color.withValues(alpha:0.09),borderRadius:BorderRadius.circular(8)),child:Text(label,style:TextStyle(color:color,fontWeight:FontWeight.w700,fontSize:11)));
}
class EmptyPanel extends StatelessWidget{
 const EmptyPanel(this.message,{super.key});final String message;
 @override Widget build(BuildContext context)=>Card(child:Padding(padding:const EdgeInsets.all(28),child:Column(children:[const Icon(Icons.inbox_outlined,color:textMuted,size:36),const SizedBox(height:12),Text(message,textAlign:TextAlign.center,style:const TextStyle(color:textMuted))])));
}
String shortDate(String d)=>d.split('-').reversed.join('/');
String compactHours(Duration d)=>'${(d.inMinutes/60).toStringAsFixed(1)} h';
String requestLabel(dynamic status)=>switch(status){'approved'=>'Đã duyệt','rejected'=>'Từ chối',_=>'Chờ duyệt'};
Color requestColor(dynamic status)=>switch(status){'approved'=>const Color(0xFF12805C),'rejected'=>const Color(0xFFCA4556),_=>const Color(0xFFB77813)};
